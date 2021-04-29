<?php
/**
 * Pinterest Vendor API
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
 * Base Class for registering our endpoints.
 */
class VendorAPI {

	/**
	 * The API namespace
	 *
	 * @var string
	 */
	private $api_namespace = \PINTEREST_FOR_WOOCOMMERCE_API_NAMESPACE . '/v';

	/**
	 * The API version
	 *
	 * @var string
	 */
	private $api_version = \PINTEREST_FOR_WOOCOMMERCE_API_VERSION;

	/**
	 * The base of the endpoint
	 *
	 * @var string
	 */
	public $base;

	/**
	 * The endpoint's methods
	 *
	 * @var string
	 */
	public $methods = 'POST';

	/**
	 * The endpoint_callback
	 *
	 * @var string
	 */
	public $endpoint_callback;

	/**
	 * Returns the namespace.
	 *
	 * @return string
	 */
	public function get_namespace() {
		return $this->api_namespace;
	}

	/**
	 * Returns the version.
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->api_version;
	}

	/**
	 * Register endpoint Routes
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {

		$namespace = $this->api_namespace . $this->api_version;

		register_rest_route(
			$namespace,
			'/' . $this->base,
			array(
				array(
					'methods'             => $this->methods,
					'callback'            => array( $this, $this->endpoint_callback ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
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

		return true;
	}

	/**
	 * Return is user has permissions to edit option
	 *
	 * @param string          $option the option to check for permission.
	 * @param WP_REST_Request $request The request.
	 *
	 * @return boolean
	 */
	public function user_has_option_permission( $option, $request ) {

		$permissions = apply_filters( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_rest_api_option_permissions', array(), $option, $request );
		if ( isset( $permissions[ $option ] ) ) {
			return $permissions[ $option ];
		}

		return current_user_can( 'manage_options' );
	}
}
