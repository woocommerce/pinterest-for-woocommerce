<?php
/**
 * Handle Pinterest Tags
 *
 * @package     Pinterest_For_Woocommerce/API
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\API;

use Exception;
use Throwable;
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
	 * Get the tracking tags for the Advertiser.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return mixed
	 *
	 * @throws \Exception PHP Exception.
	 */
	public function get_tags( WP_REST_Request $request ) {
		try {
			$ad_account_id = $request->get_param( 'advrtsr_id' );
			if ( ! $ad_account_id ) {
				throw new Exception( esc_html__( 'Advertiser missing', 'pinterest-for-woocommerce' ), 400 );
			}

			try {
				$tags = APIV5::get_advertiser_tags( $ad_account_id );
			} catch ( Throwable $th ) {
				throw new Exception( esc_html__( 'Response error', 'pinterest-for-woocommerce' ), 400 );
			}

			$tags = $tags['items'] ?? array();
			if ( empty( $tags ) ) {
				try {
					$tag  = APIV5::create_tag( $ad_account_id );
					$tags = array( $tag );
				} catch ( Throwable $th ) {
					throw new Exception( esc_html__( 'Could not create a tag. Please check the logs for additional information.', 'pinterest-for-woocommerce' ), 400 );
				}
			}

			return array_map(
				function ( $tag ) {
					return array(
						'id'   => $tag['id'],
						'name' => $tag['name'],
					);
				},
				$tags
			);
		} catch ( Throwable $th ) {
			/* Translators: The error description as returned from the API */
			$error_message = sprintf( esc_html__( 'No tracking tag available. [%s]', 'pinterest-for-woocommerce' ), $th->getMessage() );
			return new \WP_Error( \PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_tags_error', $error_message, array( 'status' => $th->getCode() ) );
		}
	}
}
