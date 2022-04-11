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
 * Endpoint handling Options.
 */
class Options extends VendorAPI {

	/**
	 * Initialize class
	 */
	public function __construct() {

		$this->base                        = 'settings';
		$this->supports_multiple_endpoints = true;
		$this->endpoint_callbacks_map      = array(
			'get_settings' => WP_REST_Server::READABLE,
			'set_settings' => WP_REST_Server::CREATABLE,
		);

		$this->register_routes();
	}


	/**
	 * Handle get settings.
	 *
	 * @return mixed
	 *
	 * @throws \Exception PHP Exception.
	 */
	public function get_settings() {
		return array(
			PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME => Pinterest_For_Woocommerce()::get_settings( true ),
		);
	}


	/**
	 * Handle get settings.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return array|WP_Error
	 *
	 * @throws \Exception PHP Exception.
	 */
	public function set_settings( WP_REST_Request $request ) {
		if ( ! $request->has_param( PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME ) || ! is_array( $request->get_param( PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME ) ) ) {
			return array(
				'success' => false,
			);
		}

		if ( ! Pinterest_For_Woocommerce()::save_settings( $request->get_param( PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME ) ) ) {
			return array(
				'success' => false,
			);
		}

		return array(
			'success' => true,
		);
	}
}
