<?php
/**
 * API Auth
 *
 * @package     Pinterest_For_Woocommerce/API
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\API;

use Automattic\WooCommerce\Pinterest\Logger;
use Throwable;
use WP_HTTP_Response;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the endpoint to which we are returned to, after being authorized by Pinterest.
 */
class Auth extends VendorAPI {

	/**
	 * Initiate class.
	 */
	public function __construct() {

		$this->base              = \PINTEREST_FOR_WOOCOMMERCE_API_AUTH_ENDPOINT;
		$this->endpoint_callback = 'connect_callback';
		$this->methods           = 'GET';

		$this->register_routes();
	}


	/**
	 * Authenticate request
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return boolean
	 */
	public function permissions_check( WP_REST_Request $request ) {

		$nonce = $request->get_param( 'state' ) ?? '';

		/*
		 * Check if the nonce is valid. We grab the nonce from the transient because wp_verify_nonce() in REST API call
		 * is generated for user 0 and therefore it always returns false.
		 */
		return get_transient( \PINTEREST_FOR_WOOCOMMERCE_CONNECT_NONCE ) === $nonce;
	}



	/**
	 * When we got a permissions check failure, Hijack the rest_pre_serve_request filter
	 * to sent the user to the settings page instead of showing a white page with the printed REST response
	 *
	 * @param bool             $served  Whether the request has already been served. Default false.
	 * @param WP_HTTP_Response $result  Result to send to the client. Usually a `WP_REST_Response`.
	 * @param WP_REST_Request  $request Request used to generate the response.
	 * @return bool
	 */
	public function redirect_to_settings_page( $served, $result, $request ) {

		if ( 401 === $result->get_status() ) {
			$error_message = esc_html__( 'Something went wrong with your attempt to authorize this App. Please try again.', 'pinterest-for-woocommerce' );
			wp_safe_redirect( add_query_arg( 'error', rawurlencode( $error_message ), $this->get_redirect_url( $request->get_param( 'view' ), true ) ) );
			exit;
		}

		return $served;
	}

	/**
	 * REST Route callback function for POST requests.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request.
	 */
	public function connect_callback( WP_REST_Request $request ) {

		$error      = $request->has_param( 'error' ) ? sanitize_text_field( $request->get_param( 'error' ) ) : '';
		$token_data = $request->get_param( 'token_data' );
		$info       = $request->get_param( 'info' );

		// Check if there is an error.
		if ( ! empty( $error ) ) {
			$this->log_error_and_redirect( $request, $error );
		}

		if ( empty( $token_data ) ) {
			$error = esc_html__( 'Token data missing, please try again later.', 'pinterest-for-woocommerce' );
			$this->log_error_and_redirect( $request, $error );
		}

		if ( empty( $info ) ) {
			$error = esc_html__( 'Connection information missing, please try again later.', 'pinterest-for-woocommerce' );
			$this->log_error_and_redirect( $request, $error );
		}

		$token_string = base64_decode( $token_data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$token_data   = (array) json_decode( urldecode( $token_string ) );

		Pinterest_For_Woocommerce()::save_token_data( $token_data );

		$info_string = base64_decode( $info ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$info_data   = (array) json_decode( urldecode( $info_string ) );
		$features    = (array) $info_data['feature_flags'] ?? array();

		$this->apply_oauth_flow_features( $features );

		unset( $info_data['feature_flags'] );

		Pinterest_For_Woocommerce()::save_connection_info_data( $info_data );

		try {
			/**
			 * Actions to perform after getting the authorization token.
			 *
			 * @since 1.4.0
			 */
			do_action( 'pinterest_for_woocommerce_token_saved' );
		} catch ( Throwable $th ) {
			$error = esc_html__( 'There was an error getting the account data. Please try again later.', 'pinterest-for-woocommerce' );
			$this->log_error_and_redirect( $request, $error );
		}

		wp_safe_redirect( $this->get_redirect_url( $request->get_param( 'view' ) ) );
		exit;
	}

	/**
	 * Applies the features from the OAuth flow to the plugin settings.
	 *
	 * @param array $features {
	 *      The features selected by the merchant during the OAuth flow.
	 *
	 *      @type bool $tags    Whether Pinterest Tag was enabled.
	 *      @type bool $CAPI    Whether Conversions API was enabled.
	 *      @type bool $catalog Whether Catalog synchronisation was enabled.
	 * }
	 */
	private function apply_oauth_flow_features( array $features ): void {
		Pinterest_For_Woocommerce()::save_setting( 'track_conversions', $features['tags'] ?? false );
		Pinterest_For_Woocommerce()::save_setting( 'track_conversions_capi', false );
		Pinterest_For_Woocommerce()::save_setting( 'product_sync_enabled', $features['catalog'] ?? false );
	}

	/**
	 * Logs the error and redirects to the settings page.
	 *
	 * @param WP_REST_Request $request The request.
	 * @param string          $error   The error message.
	 */
	public function log_error_and_redirect( WP_REST_Request $request, $error ) {
		$error_args = '&error=' . $error;
		Logger::log( wp_json_encode( $error ), 'error', null, true );
		wp_safe_redirect( $this->get_redirect_url( $request->get_param( 'view' ), ! empty( $error ) ) . $error_args );
		exit;
	}

	/**
	 * Returns the redirect URI based on the current request's parameters and plugin settings.
	 *
	 * @param string  $view      The context of the view.
	 * @param boolean $has_error Whether there was an error with the auth process.
	 *
	 * @return string
	 */
	private function get_redirect_url( $view = null, $has_error = false ) {

		$query_args = array(
			'page' => 'wc-admin',
			'path' => '/pinterest/onboarding',
			'step' => $has_error || ! Pinterest_For_Woocommerce()::is_business_connected() ? 'setup-account' : 'claim-website',
		);

		if ( ! empty( $view ) ) {
			$query_args['view'] = sanitize_key( $view );
		}

		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
		// nosemgrep: audit.php.wp.security.xss.query-arg
		return add_query_arg(
			$query_args,
			admin_url( 'admin.php' )
		);
	}
}
