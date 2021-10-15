<?php
/**
 * Return Pinterest Feed health status.
 *
 * @package     Pinterest_For_Woocommerce/API
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\API;

use \WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Endpoint used to check the Health status of the connected Merchant object.
 */
class HealthCheck extends VendorAPI {

	/**
	 * Initialize class
	 */
	public function __construct() {
		$this->base              = 'health';
		$this->endpoint_callback = 'health_check';
		$this->methods           = WP_REST_Server::READABLE;

		$this->register_routes();
	}


	/**
	 * Get the merchant object from the API and return the status, and if exists, the dissapproval rationale.
	 *
	 * @return array
	 *
	 * @throws \Exception PHP Exception.
	 */
	public function health_check() {

		try {

			$merchant_id = Pinterest_For_Woocommerce()::get_data( 'merchant_id' );

			if ( empty( $merchant_id ) ) {
				// Get merchant from advertiser object.
				$merchant_id = Base::get_merchant_id_from_advertiser();
			}

			if ( empty( $merchant_id ) ) {
				throw new \Exception( esc_html__( 'No merchant configured on Pinterest.', 'pinterest-for-woocommerce' ), 200 );
			}

			$merchant = Base::get_merchant( $merchant_id );

			if ( 'success' !== $merchant['status'] || empty( $merchant['data']->product_pin_approval_status ) ) {
				throw new \Exception( esc_html__( 'Could not get approval status from Pinterest.', 'pinterest-for-woocommerce' ), 200 );
			}

			$response = array(
				'status' => esc_html( $merchant['data']->product_pin_approval_status ),
			);

			if ( isset( $merchant['data']->product_pin_approval_status_rationale ) ) {
				$response['reason'] = $merchant['data']->product_pin_approval_status_rationale;
			}

			return $response;

		} catch ( \Throwable $th ) {

			/* Translators: The error description as returned from the API */
			$error_message = sprintf( esc_html__( 'Could not fetch account status. [%s]', 'pinterest-for-woocommerce' ), $th->getMessage() );

			return array(
				'status'  => 'error',
				'message' => $error_message,
				'code'    => $th->getCode(),
			);
		}
	}
}
