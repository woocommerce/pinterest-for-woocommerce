<?php
/**
 * API Auth
 *
 * @package     Pinterest_For_Woocommerce/API
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\API;

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
		if ( empty( $_GET['control'] ) || empty( $control ) || $control !== $_GET['control'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		delete_transient( \PINTEREST_FOR_WOOCOMMERCE_AUTH );

		return true;
	}


	/**
	 * REST Route callback function for POST requests.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request.
	 */
	public function oauth_callback( WP_REST_Request $request ) {

		$error      = empty( $_GET['error'] ) ? '' : sanitize_text_field( wp_unslash( $_GET['error'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error_args = '';

		if ( empty( $_GET['pinterestv3_access_token'] ) || empty( $_GET['control'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$error = esc_html__( 'Empty response, please try again later.', 'pinterest-for-woocommerce' );
		}

		if ( ! empty( $error ) ) {
			$error_args = '&error=' . $error;
			Base::log( 'error', wp_json_encode( $error ) );
		}

		// Save token information.
		if ( empty( $error ) ) {

			Pinterest_For_Woocommerce()::save_token(
				array(
					'access_token' => sanitize_text_field( wp_unslash( $_GET['pinterestv3_access_token'] ) ), //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				)
			);

			Base::instance()->set_token();

			do_action( 'pinterest_for_woocommerce_account_updated' );
		}

		$redirect_url      = admin_url( 'admin.php?page=' . \PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE );
		$is_setup_complete = Pinterest_For_Woocommerce()::get_setting( 'is_setup_complete', true );

		if ( empty( $is_setup_complete ) || 'no' === $is_setup_complete ) {
			$step         = empty( $error ) ? 'verify-domain' : 'setup-account';
			$redirect_url = add_query_arg(
				array(
					'page' => 'wc-admin',
					'task' => 'setup-pinterest',
					'step' => $step,
				),
				get_admin_url( null, 'admin.php' )
			);

			if ( ! empty( $_GET['view'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended --- not needed
				$redirect_url = add_query_arg(
					array(
						'view' => sanitize_key( $_GET['view'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended --- not needed
					),
					$redirect_url
				);
			}
		}

		wp_safe_redirect( $redirect_url . $error_args );
		exit;
	}
}
