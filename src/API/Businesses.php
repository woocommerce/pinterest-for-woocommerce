<?php
/**
 * Handle Pinterest Businesses
 *
 * @package     Pinterest_For_Woocommerce/API
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\API;

use \WP_REST_Server;
use \WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Endpoint handing Pinterest Linked Business Accounts.
 */
class Businesses extends VendorAPI {

	/**
	 * Initialize class
	 */
	public function __construct() {

		$this->base              = 'businesses';
		$this->endpoint_callback = 'get_businesses';
		$this->methods           = WP_REST_Server::READABLE;

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
		return current_user_can( 'manage_woocommerce' );
	}


	/**
	 * Get the Linked Business Accounts assigned to the authorized Pinterest account.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return array
	 *
	 * @throws \Exception PHP Exception.
	 */
	public function get_businesses( WP_REST_Request $request ) {

		try {

			Pinterest_For_Woocommerce()::fetch_linked_businesses();
			$businesses = Pinterest_For_Woocommerce()::get_linked_businesses();

			return $businesses;

		} catch ( \Throwable $th ) {

			/* Translators: The error description as returned from the API */
			$error_message = sprintf( esc_html__( 'Could not fetch Linked Business Accounts for Pinterest account ID. [%s]', 'pinterest-for-woocommerce' ), $th->getMessage() );

			return new \WP_Error( \PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_businesses_error', $error_message, array( 'status' => $th->getCode() ) );
		}
	}
}
