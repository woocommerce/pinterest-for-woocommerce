<?php
/**
 * Handle a Disconnection request.
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
 * Registers the endpoint which will handle the disconnection.
 */
class AuthDisconnect extends VendorAPI {

	/**
	 * Initiate class.
	 */
	public function __construct() {

		$this->base              = 'auth_disconnect';
		$this->endpoint_callback = 'handle_disconnect';
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
		return current_user_can( 'manage_options' );
	}

	/**
	 * REST Route callback function for POST requests.
	 *
	 * @since 1.0.0
	 */
	public function handle_disconnect() {
		return array(
			'disconnected' => Pinterest_For_Woocommerce()::save_data( 'crypto_encoded_key', null ) || Pinterest_For_Woocommerce()::save_data( 'token', null ),
		);
	}
}
