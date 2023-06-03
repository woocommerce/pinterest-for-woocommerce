<?php
/**
 * API Options
 *
 * @package     Pinterest_For_Woocommerce/API
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\API;

use Automattic\WooCommerce\Pinterest\Logger as Logger;

use Automattic\WooCommerce\Pinterest\PinterestApiException;
use Exception;
use Pinterest_For_Woocommerce;
use WP_Error;
use WP_REST_Server;

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
	private static $verification_attempts_remaining = 3;

	/**
	 * Initialize class
	 */
	public function __construct() {

		$this->base              = 'domain_verification';
		$this->endpoint_callback = 'maybe_handle_verification';
		$this->methods           = WP_REST_Server::EDITABLE;

		$this->register_routes();
	}


	/**
	 * Handle domain verification by triggering the realtime verification process
	 * using the Pinterest API.
	 *
	 * @since x.x.x
	 *
	 * @return mixed
	 *
	 * @throws Exception PHP Exception.
	 */
	public function maybe_handle_verification() {
		try {
			$result       = array();
			$account_data = Pinterest_For_Woocommerce()::get_setting( 'account_data', true );
			if ( ! Pinterest_For_Woocommerce::is_domain_verified() ) {
				$domain_verification_data = APIV5::domain_verification_data();
				Pinterest_For_Woocommerce()::save_data( 'verification_data', $domain_verification_data );
				$parsed_website = wp_parse_url( get_home_url() );
				$result         = APIV5::domain_metatag_verification_request( $parsed_website['host'] . $parsed_website['path'] );
				if ( 'success' === $result['status'] ) {
					$account_data['verified_user_websites'][] = $result['website'];
					$account_data['is_any_website_verified']  = 0 < count( $account_data['verified_user_websites'] );
					Pinterest_For_Woocommerce()::save_setting( 'account_data', $account_data );
				}
			}
			return array_merge( $result, array( 'account_data' => $account_data ) );
		} catch ( PinterestApiException $th ) {
			return new WP_Error(
				'pinterest-for-woocommerce_verification_error',
				$th->getMessage(),
				array(
					'status'         => $th->getCode(),
					'pinterest_code' => method_exists( $th, 'get_pinterest_code' ) ? $th->get_pinterest_code() : 0,
				)
			);
		}
	}


	/**
	 * Triggers the realtime verification process using the Pinterst API.
	 *
	 * @return mixed
	 *
	 * @throws \Exception PHP Exception.
	 */
	public static function trigger_domain_verification() {
		static $verification_data;

		try {

			if ( is_null( $verification_data ) ) {
				// Get verification code from pinterest.
				$verification_data = Base::domain_verification_data();
			}

			if ( 'success' === $verification_data['status'] && ! empty( $verification_data['data']->verification_code ) ) {

				Pinterest_For_Woocommerce()::save_data( 'verification_data', (array) $verification_data['data'] );

				$result = Base::trigger_verification();

				if ( 'success' === $result['status'] ) {
					$account_data = Pinterest_For_Woocommerce()::update_account_data();
					return array_merge( (array) $result['data'], array( 'account_data' => $account_data ) );
				}

				throw new \Exception( 'Meta tag verification failed', 409 );

			}

			throw new \Exception( 'Domain verification failed', 406 );

		} catch ( \Throwable $th ) {

			$error_code = $th->getCode() >= 400 ? $th->getCode() : 400;

			if ( 403 === $error_code && self::$verification_attempts_remaining > 0 ) {
				self::$verification_attempts_remaining--;
				Logger::log( sprintf( 'Retrying domain verification in 5 seconds. Attempts left: %d', self::$verification_attempts_remaining ), 'debug' );
				sleep( 5 );
				return call_user_func( __METHOD__ );
			}

			return new \WP_Error(
				\PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_verification_error',
				$th->getMessage(),
				array(
					'status'         => $error_code,
					'pinterest_code' => method_exists( $th, 'get_pinterest_code' ) ? $th->get_pinterest_code() : 0,
				)
			);

		}
	}
}
