<?php
/**
 * Handle Pinterest Tags
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
 * Endpoint handing Pinterest Tags.
 */
class Tags extends VendorAPI {

	/**
	 * Initialize class
	 */
	public function __construct() {

		$this->base              = 'tags';
		$this->endpoint_callback = 'get_tags';
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
		return current_user_can( 'manage_options' );
	}


	/**
	 * Get the tracking tags for the Advertiser.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return mixed
	 *
	 * @throws \Exception PHP Exception.
	 */
	public function get_tags( WP_REST_Request $request ) {

		try {

			$tags          = array();
			$advertiser_id = $request->get_param( 'advertiser_id' );

			if ( ! $advertiser_id ) {
				throw new \Exception( esc_html__( 'Advertiser missing', 'pinterest-for-woocommerce' ), 400 );
			}

			$response = Base::get_advertiser_tags( $advertiser_id );

			if ( 'success' !== $response['status'] ) {
				throw new \Exception( esc_html__( 'Response error', 'pinterest-for-woocommerce' ), 400 );
			}

			$tags = $response['data'];

			if ( empty( $tags ) ) {
				// No tag created yet. Lets create one.
				$tag = Base::create_tag( $advertiser_id );

				$tags[] = $tag;
			}

			Pinterest_For_Woocommerce()::save_setting( 'account_tags', $tags );

			return $tags;

		} catch ( \Throwable $th ) {

			/* Translators: The error description as returned from the API */
			$error_message = sprintf( esc_html__( 'Could not fetch tracking tags for the given advertiser. [%s]', 'pinterest-for-woocommerce' ), $th->getMessage() );

			return new \WP_Error( \PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_tags_error', $error_message, array( 'status' => $th->getCode() ) );

		}
	}
}
