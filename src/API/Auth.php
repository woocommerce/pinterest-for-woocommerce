<?php
/**
 * API Auth
 *
 * @package     Pinterest_For_Woocommerce/API
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\API;

use Automattic\WooCommerce\Pinterest\Logger as Logger;

use \WP_REST_Request;

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
		$this->endpoint_callback = 'oauth_callback';
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

		$control = get_transient( \PINTEREST_FOR_WOOCOMMERCE_AUTH );

		if ( ! $control || ! $request->has_param( 'control' ) || $control !== $request->get_param( 'control' ) ) {
			add_filter( 'rest_pre_serve_request', array( $this, 'redirect_to_settings_page' ), 10, 3 );
			return false;
		}

		delete_transient( \PINTEREST_FOR_WOOCOMMERCE_AUTH );

		return true;
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
			$error_message = esc_html__( 'Something went wrong with your attempt to authorize this App. Please try agagin.', 'pinterest-for-woocommerce' );
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
	public function oauth_callback( WP_REST_Request $request ) {

		$error_args = '';
		$error      = $request->has_param( 'error' ) ? sanitize_text_field( $request->get_param( 'error' ) ) : '';
		$token      = $request->get_param( 'pinterestv3_access_token' );
		$control    = $request->get_param( 'control' );

		if ( empty( $token ) || empty( $control ) ) {
			$error = esc_html__( 'Empty response, please try again later.', 'pinterest-for-woocommerce' );
		}

		if ( ! empty( $error ) ) {
			$error_args = '&error=' . $error;
			Logger::log( wp_json_encode( $error ), 'error' );
		}

		// Save token information.
		if ( empty( $error ) ) {

			Pinterest_For_Woocommerce()::save_token(
				array(
					'access_token' => sanitize_text_field( $token ),
				)
			);

			do_action( 'pinterest_for_woocommerce_token_saved' );
		}

		wp_safe_redirect( $this->get_redirect_url( $request->get_param( 'view' ), ! empty( $error ) ) . $error_args );
		exit;
	}

	/**
	 * Returns the redirect URI based on the current request's parameters and plugin settings.
	 *
	 * @param string $view      The context of the view.
	 * @param string $has_error Whether there was an error with the auth process.
	 *
	 * @return string
	 */
	private function get_redirect_url( $view = null, $has_error = false ) {

		$redirect_url            = admin_url( 'admin.php?page=' . \PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE );
		$is_setup_complete       = Pinterest_For_Woocommerce()::get_setting( 'is_setup_complete', true );
		$dismissed_wc_tasks      = get_option( 'woocommerce_task_list_dismissed_tasks' );
		$is_setup_task_dismissed = ! empty( $dismissed_wc_tasks ) && is_array( $dismissed_wc_tasks ) && in_array( 'setup-pinterest', $dismissed_wc_tasks, true );

		// If the setup task is dismissed, we cannot go to WC-Admin, so go to settings.
		if ( $is_setup_task_dismissed ) {
			return $redirect_url;
		}

		// If started on settings, go back to settings.
		if ( ! empty( $view ) && 'settings' === $view ) {
			return $redirect_url;
		}

		// If we have already completed onboarding, go to settings.
		if ( $is_setup_complete ) {
			return $redirect_url;
		}

		// Go to WC-Admin to render our App there.
		$step         = empty( $has_error ) ? 'claim-website' : 'setup-account';
		$redirect_url = add_query_arg(
			array(
				'page' => 'wc-admin',
				'task' => 'setup-pinterest',
				'step' => $step,
			),
			get_admin_url( null, 'admin.php' )
		);

		if ( ! empty( $view ) ) {
			$redirect_url = add_query_arg(
				array(
					'view' => sanitize_key( $view ),
				),
				$redirect_url
			);
		}

		return $redirect_url;
	}
}
