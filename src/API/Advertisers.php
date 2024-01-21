<?php
/**
 * Handle Pinterest Advertisers
 *
 * @package     Pinterest_For_Woocommerce/API
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\API;

use Exception;
use Throwable;
use WP_Error;
use \WP_REST_Server;
use \WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Endpoint handing Pinterest advertisers.
 */
class Advertisers extends VendorAPI {

	/**
	 * Initialize class
	 */
	public function __construct() {

		$this->base              = 'tagowners';
		$this->endpoint_callback = 'get_advertisers';
		$this->methods           = WP_REST_Server::READABLE;

		$this->register_routes();
	}


	/**
	 * Get the advertisers assigned to the authorized Pinterest account.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return array|WP_Error
	 *
	 * @throws Exception PHP Exception.
	 */
	public function get_advertisers( WP_REST_Request $request ) {
		try {
			$advertisers = APIV5::get_advertisers();
			return array(
				'advertisers' => array_map(
					function ( $item ) {
						return array(
							'id'   => $item['id'],
							'name' => $item['name'],
						);
					},
					$advertisers['items']
				),
			);
		} catch ( Throwable $th ) {
			/* Translators: The error description as returned from the API */
			$error_message = sprintf( esc_html__( 'Could not fetch advertisers for Pinterest account ID. [%s]', 'pinterest-for-woocommerce' ), $th->getMessage() );
			return new WP_Error( \PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_advertisers_error', $error_message, array( 'status' => $th->getCode() ) );
		}
	}
}
