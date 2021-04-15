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

				if ( 'success' === $result['status'] ) {
					$account_data = Pinterest_For_Woocommerce()::update_account_data();
					return array_merge( (array) $result['data'], array( 'account_data' => $account_data ) );
				}
			}

			throw new \Exception();

		} catch ( \Throwable $th ) {

			/* Translators: The error description as returned from the API */
			$error_message = sprintf( esc_html__( 'Your domain could not be automatically verified. [%s]', 'pinterest-for-woocommerce' ), $th->getMessage() );

			return new \WP_Error( \PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_verification_error', $error_message );

		}
	}
}
