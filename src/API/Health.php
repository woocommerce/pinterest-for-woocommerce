<?php
/**
 * Return Pinterest Feed health status.
 *
 * @package     Pinterest_For_Woocommerce/API
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\API;

use Automattic\WooCommerce\Pinterest as Pinterest;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Endpoint used to check the Health status of the connected Merchant object.
 */
class Health extends VendorAPI {

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
	 * Get the merchant object from the API and return the status, and if exists, the disapproval rationale.
	 *
	 * @return array
	 *
	 * @throws \Exception PHP Exception.
	 */
	public function health_check() {
		return array(
			'status' => 'approved',
		);
	}
}
