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

class Get extends VendorAPI {

	public function __construct() {

		$this->base              = 'options';
		$this->endpoint_callback = 'get_options';
		$this->methods           = WP_REST_Server::READABLE;

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

		$params = explode( ',', $request['options'] );
		if ( ! isset( $request['options'] ) || ! is_array( $params ) ) {
			return new WP_Error( \PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_rest_cannot_view', esc_html__( 'You must supply an array of options.', 'pinterest-for-woocommerce' ), 500 );
		}

		foreach ( $params as $option ) {
			if ( ! $this->user_has_option_permission( $option, $request ) ) {
				return new \WP_Error( \PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_rest_cannot_view', esc_html__( 'Sorry, you cannot view these options.', 'pinterest-for-woocommerce' ), array( 'status' => rest_authorization_required_code() ) );
			}
		}

		return true;
	}


	public function get_options( $request ) {

		$params  = explode( ',', $request['options'] );
		$options = array();
		if ( ! is_array( $params ) ) {
			return array();
		}

		foreach ( $params as $option ) {
			$options[ $option ] = get_option( $option );
		}

		return $options;
	}
}

