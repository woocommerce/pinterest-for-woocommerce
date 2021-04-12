<?php
/**
 * API Options
 *
 * @author      WooCommerce
 * @category    API
 * @package     Pinterest_For_Woocommerce/API
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\API;

use \WP_REST_Server;
use \WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DomainVerification extends VendorAPI {

	public function __construct() {

		$this->base              = 'domain_verification';
		$this->endpoint_callback = 'handle_verification';
		$this->methods           = WP_REST_Server::EDITABLE;

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
		return current_user_can( 'manage_options' );
	}


	public function handle_verification() {

		try {

			// Get verification code from pinterest.
			$verification_data = Base::domain_verification_data();

			if ( 'success' === $verification_data['status'] && ! empty( $verification_data['data']->verification_code ) ) {
				Pinterest_For_Woocommerce()::save_setting( 'verfication_code', sanitize_text_field( $verification_data['data']->verification_code ) );

				$result = Base::trigger_verification();

				do_action( 'pinterest_for_woocommerce_account_updated' );

				if ( 'success' === $result['status'] ) {
					return $result['data'];
				}
			}

			throw new \Exception();

		} catch ( \Throwable $th ) {

			return new \WP_Error( \PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_verification_error', esc_html__( 'Your domain could not be automatically verified. Please checks the logs for additional information.', 'pinterest-for-woocommerce' ) );

		}
	}
}
