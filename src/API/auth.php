<?php
/**
 * API Auth
 *
 * @author      WooCommerce
 * @category    API
 * @package     Pinterest_For_Woocommerce/API
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\API;

use \WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Auth extends VendorAPI {

	public function __construct() {

		$this->base              = PINTEREST_FOR_WOOCOMMERCE_API_AUTH_ENDPOINT;
		$this->endpoint_callback = 'oauth_callback';
		$this->methods           = 'GET';

		$this->register_routes();
	}


	/**
	 * Authenticate request
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return boolean
	 */
	public function permissions_check( WP_REST_Request $request ) {

		$control = get_transient( PINTEREST_FOR_WOOCOMMERCE_AUTH );
		if ( empty( $_GET['control'] ) || empty( $control ) || $control !== $_GET['control'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		delete_transient( PINTEREST_FOR_WOOCOMMERCE_AUTH );

		return true;
	}


	/**
	 * REST Route callback function for POST requests.
	 * @since 1.0.2
	 */
	public function oauth_callback( WP_REST_Request $request ) {

		$error      = empty( $_GET['error'] ) ? '' : $_GET['error']; //phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification
		$error_args = '';

		if ( empty( $_GET['pinterestv3_access_token'] ) || empty( $_GET['control'] ) ) { //phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification
			$error = esc_html__( 'Empty response, please try again later.', 'pinterest-for-woocommerce' );
		}

		if ( ! empty( $error ) ) {
			$error_args = '&error=' . $error;
			Base::log( 'error', wp_json_encode( $error ) );
		}

		// Save token information
		if ( empty( $error ) ) {

			Pinterest_For_Woocommerce()::save_token(
				array(
					'access_token' => $_GET['pinterestv3_access_token'], // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				)
			);
		}

		$redirect_url      = admin_url( 'admin.php?page=' . PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		$is_setup_complete = Pinterest_For_Woocommerce()::get_setting( 'is_setup_complete', true );

		if ( empty( $is_setup_complete ) || 'no' === $is_setup_complete ) {
			$step         = empty( $error ) ? 'setup' : 'connect';
			$redirect_url = add_query_arg(
				array(
					'page' => PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE,
					'step' => $step,
				),
				admin_url( 'admin.php' )
			);
		}

		// Set setup as completed
		if ( empty( $error ) ) {
			Pinterest_For_Woocommerce()::save_setting( 'is_setup_complete', 'yes' );
		}

		wp_safe_redirect( $redirect_url . $error_args );
		exit;
	}
}
