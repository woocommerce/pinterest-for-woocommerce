<?php
/**
 * API Options
 *
 * @author      WooCommerce
 * @category    API
 * @package     Pinterest_For_Woocommerce/API
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\API\Options;

use Automattic\WooCommerce\Pinterest\API\VendorAPI;
use \WP_REST_Server;
use \WP_REST_Request;
use \WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Update extends VendorAPI {

	public function __construct() {

		$this->base              = 'options';
		$this->endpoint_callback = 'update_options';
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

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new WP_Error( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_rest_cannot_view', __( 'You must supply an array of options and values.', 'pinterest-for-woocommerce' ), 500 );
		}

		foreach ( $params as $option_name => $option_value ) {
			if ( ! $this->user_has_option_permission( $option_name, $request ) ) {
				return new \WP_Error( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_rest_cannot_view', __( 'Sorry, you cannot manage these options.', 'pinterest-for-woocommerce' ), array( 'status' => rest_authorization_required_code() ) );
			}
		}

		return true;
	}


	public function update_options( $request ) {

		$params  = $request->get_json_params();
		$updated = array();
		if ( ! is_array( $params ) ) {
			return array();
		}

		foreach ( $params as $key => $value ) {
			$updated[ $key ] = update_option( $key, $value );
		}

		return $updated;
	}
}

