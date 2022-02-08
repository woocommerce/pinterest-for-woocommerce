<?php
/**
 * Handle Pinterest Advertiser Connect
 *
 * @package     Pinterest_For_Woocommerce/API
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\API;

use \WP_REST_Server;
use \WP_REST_Request;
use \WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Endpoint handing Pinterest advertiser.
 */
class AdvertiserConnect extends VendorAPI {

	/**
	 * Initialize class
	 */
	public function __construct() {

		$this->base              = 'tagowner';
		$this->endpoint_callback = 'connect_advertiser';
		$this->methods           = WP_REST_Server::CREATABLE;

		$this->register_routes();
	}


	/**
	 * Connect the selected advertiser with Pinterest account.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return array|WP_Error
	 *
	 * @throws \Exception PHP Exception.
	 */
	public function connect_advertiser( WP_REST_Request $request ) {

		try {

			$advertiser_id = $request->has_param( 'advrtsr_id' ) ? $request->get_param( 'advrtsr_id' ) : false;
			$tag_id        = $request->has_param( 'tag_id' ) ? $request->get_param( 'tag_id' ) : false;

			if ( ! $advertiser_id || ! $tag_id ) {
				throw new \Exception( esc_html__( 'Missing advertiser or tag parameters.', 'pinterest-for-woocommerce' ), 400 );
			}

			$is_connected = Pinterest_For_Woocommerce()::get_data( 'is_advertiser_connected' );

			// Check if advertiser is already connected.
			if ( $is_connected ) {
				return array(
					'connected'   => $advertiser_id,
					'reconnected' => false,
				);
			}

			// Connect new advertiser and tag.
			return self::connect_advertiser_and_tag( $advertiser_id, $tag_id );

		} catch ( \Throwable $th ) {

			/* Translators: The error description as returned from the API */
			$error_message = sprintf( esc_html__( 'Could not connect advertiser with Pinterest. [%s]', 'pinterest-for-woocommerce' ), $th->getMessage() );

			return new WP_Error( \PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_advertiser_connect_error', $error_message, array( 'status' => $th->getCode() ) );
		}
	}


	/**
	 * Connect an advertiser and a tag to the platform.
	 *
	 * @param string $advertiser_id The ID of the advertiser.
	 * @param string $tag_id        The ID of the tag.
	 *
	 * @throws \Exception PHP Exception.
	 */
	public static function connect_advertiser_and_tag( $advertiser_id, $tag_id ) {

		$response = Base::connect_advertiser( $advertiser_id, $tag_id );

		if ( 'success' !== $response['status'] ) {
			throw new \Exception( esc_html__( 'The advertiser could not be connected to Pinterest.', 'pinterest-for-woocommerce' ), 400 );
		}

		if ( $advertiser_id !== $response['data']->advertiser_id ) {
			throw new \Exception( esc_html__( 'Incorrect advertiser ID.', 'pinterest-for-woocommerce' ), 400 );
		}

		Pinterest_For_Woocommerce()::save_data( 'is_advertiser_connected', true );

		return array(
			'connected'   => $response['data']->advertiser_id,
			'reconnected' => true,
		);
	}


	/**
	 * Disconnect the previous connected advertiser.
	 *
	 * @param string $connected_advertiser The ID of the connected advertiser.
	 * @param string $connected_tag        The ID of the connected tag.
	 *
	 * @throws \Exception PHP Exception.
	 */
	public static function disconnect_advertiser( $connected_advertiser, $connected_tag ) {

		try {

			$response = Base::disconnect_advertiser( $connected_advertiser, $connected_tag );

			if ( 'success' !== $response['status'] ) {
				throw new \Exception( esc_html__( 'The advertiser could not be disconnected from Pinterest.', 'pinterest-for-woocommerce' ), 400 );
			}

			Pinterest_For_Woocommerce()::save_data( 'is_advertiser_connected', false );
		} catch ( \Exception $e ) {

			throw new \Exception( esc_html__( 'The advertiser could not be disconnected from Pinterest.', 'pinterest-for-woocommerce' ), 400 );
		}
	}
}
