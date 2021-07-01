<?php
/**
 * API Options
 *
 * @package     Pinterest_For_Woocommerce/API
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\API;

use Automattic\WooCommerce\Pinterest\Logger as Logger;

use \WP_REST_Server;
use \WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Endpoint handing Domain verification.
 */
class DomainVerification extends VendorAPI {

	/**
	 * The number of remaining attempts to retry domain verification on error.
	 *
	 * @var integer
	 */
	private $verification_attempts_remaining = 3;

	/**
	 * Initialize class
	 */
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
	 * @param WP_REST_Request $request The request.
	 *
	 * @return boolean
	 */
	public function permissions_check( WP_REST_Request $request ) {
		return current_user_can( 'manage_options' );
	}


	/**
	 * Handle domain verification by triggering the realtime verification process
	 * using the Pinterst API.
	 *
	 * @return mixed
	 *
	 * @throws \Exception PHP Exception.
	 */
	public function handle_verification() {

		static $verification_data;

		try {

			if ( is_null( $verification_data ) ) {
				// Get verification code from pinterest.
				$verification_data = Base::domain_verification_data();
			}

			if ( 'success' === $verification_data['status'] && ! empty( $verification_data['data']->verification_code ) ) {

				Pinterest_For_Woocommerce()::save_data( 'verification_data', (array) $verification_data['data'] );

				$result = Base::trigger_verification();

				flush_rewrite_rules(); // Rewrite rules as we need to serve the URL for the pinterest-XXXXX.html file.

				if ( 'success' === $result['status'] ) {
					$account_data = Pinterest_For_Woocommerce()::update_account_data();
					return array_merge( (array) $result['data'], array( 'account_data' => $account_data ) );
				}
			}

			throw new \Exception();

		} catch ( \Throwable $th ) {

			$error_code = $th->getCode() >= 400 ? $th->getCode() : 400;

			if ( 403 === $error_code && $this->verification_attempts_remaining > 0 ) {
				$this->verification_attempts_remaining--;
				Logger::log( sprintf( 'Retrying domain verification. Attempts left: %d', $this->verification_attempts_remaining ), 'debug' );
				return call_user_func( __METHOD__ );
			}

			/* Translators: The error description as returned from the API */
			$error_message = sprintf( esc_html__( 'Your domain could not be automatically verified. [%s]', 'pinterest-for-woocommerce' ), $th->getMessage() );

			return new \WP_Error( \PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_verification_error', $error_message, array( 'status' => $error_code ) );

		}
	}
}
