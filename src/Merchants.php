<?php
/**
 * Pinterest for WooCommerce Merchants related helper methods
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Pinterest\API\Base;
use \Exception;
use \Throwable;

/**
 * Class Handling registration & generation of the XML product feed.
 */
class Merchants {

	/**
	 * Returns the merchant object for the current user.
	 * If a merchant already exists, either saved to the database, or is
	 * returned by the Advertisers endpoint, it will be used, otherwise an
	 * attempt to create a new one is made.
	 *
	 * @return array
	 *
	 * @throws Throwable PHP Exception.
	 * @throws Exception PHP Exception.
	 */
	public static function get_merchant() {

		$merchant          = false;
		$saved_merchant_id = Pinterest_For_Woocommerce()::get_data( 'merchant_id' );
		$merchant_id       = $saved_merchant_id;

		if ( empty( $merchant_id ) ) {
			// Get merchant from advertiser object.

			try {
				$merchant_id = self::get_merchant_id_from_advertiser();
			} catch ( Throwable $th ) {

				if ( 404 !== $th->getCode() ) {
					throw $th;
				}

				$merchant = false;
			}
		}

		if ( ! empty( $merchant_id ) ) {

			// Get merchant if a merchant id was found.
			try {
				$merchant = Base::get_merchant( $merchant_id );
			} catch ( Throwable $th ) {
				$merchant = false;
			}
		}

		if ( ! $merchant || ( 'success' !== $merchant['status'] && 650 === $merchant['code'] ) ) {  // https://developers.pinterest.com/docs/redoc/#tag/API-Response-Codes Merchant not found 650.
			// Try creating one.
			$response = self::update_or_create_merchant();

			if ( ! $response['merchant_id'] ) {
				throw new Exception( __( 'Wrong response when trying to create or update merchant.', 'pinterest-for-woocommerce' ), 400 );
			}

			$merchant_id = $response['merchant_id'];

			try {
				$merchant = Base::get_merchant( $merchant_id );
			} catch ( Throwable $th ) {
				throw new Exception( __( 'There was an error trying to get the merchant object.', 'pinterest-for-woocommerce' ), 400 );
			}
		}

		if ( ! $merchant || 'success' !== $merchant['status'] || ! $merchant['data'] ) {
			throw new Exception( __( 'Response error when trying create a merchant or update the existing one.', 'pinterest-for-woocommerce' ), 400 );
		}

		// Update merchant id if it is different from the stored in DB.
		if ( $saved_merchant_id !== $merchant['data']->id ) {
			Pinterest_For_Woocommerce()::save_data( 'merchant_id', $merchant['data']->id );
		}

		return $merchant;
	}


	/**
	 * Gets the merchant ID of the authenticated user from the data returned on the Advertisers endpoint.
	 *
	 * @return string
	 *
	 * @throws Exception PHP exception.
	 */
	private static function get_merchant_id_from_advertiser() {
		$advertisers = API\Base::get_advertisers();

		if ( 'success' !== $advertisers['status'] ) {
			throw new Exception( __( 'Response error when trying to get advertisers.', 'pinterest-for-woocommerce' ), 400 );
		}

		$advertiser = reset( $advertisers['data'] ); // All advertisers assigned to a user share the same merchant_id.

		if ( empty( $advertiser->merchant_id ) ) {
			throw new Exception( __( 'No merchant returned in the advertiser\'s response.', 'pinterest-for-woocommerce' ), 404 );
		}

		return $advertiser->merchant_id;
	}


	/**
	 * Creates a merchant for the authenticated user or updates the existing one.
	 * Returns an array with the merchant_id and the registered feed_id.
	 *
	 * @return array
	 *
	 * @throws Exception PHP Exception.
	 */
	public static function update_or_create_merchant() {

		$local_feed = ProductFeedStatus::get_local_feed();

		$merchant_name = apply_filters( 'pinterest_for_woocommerce_default_merchant_name', esc_html__( 'Auto-created by Pinterest for WooCommerce', 'pinterest-for-woocommerce' ) );

		$args = array(
			'merchant_domains' => get_home_url(),
			'feed_location'    => $local_feed['feed_url'],
			'feed_format'      => 'XML',
			'country'          => Pinterest_For_Woocommerce()::get_base_country() ?? 'US',
			'locale'           => str_replace( '_', '-', determine_locale() ),
			'currency'         => get_woocommerce_currency(),
			'merchant_name'    => $merchant_name,
		);

		// The response only contains the merchant id.
		$response = API\Base::update_or_create_merchant( $args );

		if ( 'success' !== $response['status'] ) {
			throw new Exception( __( 'Response error when trying create a merchant or update the existing one.', 'pinterest-for-woocommerce' ), 400 );
		}

		$registered_feed = Feeds::is_local_feed_registered( $response['data'] );

		// Update the registered feed id setting.
		Pinterest_For_Woocommerce()::save_data( 'feed_registered', $registered_feed );

		return array(
			'merchant_id' => $response['data'],
			'feed_id'     => $registered_feed,
		);
	}

}
