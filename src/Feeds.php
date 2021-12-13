<?php
/**
 * Pinterest for WooCommerce Feeds related helper methods
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Handling registration & generation of the XML product feed.
 */
class Feeds {

	/**
	 * Get a specific merchant feed using the given arguments.
	 *
	 * @param string $merchant_id The merchant ID the feed belongs to.
	 * @param string $feed_id     The ID of the feed.
	 *
	 * @return mixed
	 *
	 * @throws \Exception PHP Exception.
	 */
	public static function get_merchant_feed( $merchant_id, $feed_id ) {
		try {

			$feeds = API\Base::get_merchant_feeds( $merchant_id );

			if ( 'success' !== $feeds['status'] ) {
				throw new \Exception( esc_html__( 'Could not get feed info.', 'pinterest-for-woocommerce' ) );
			}

			if ( ! is_array( $feeds['data'] ) ) {
				throw new \Exception( esc_html__( 'Wrong feed info.', 'pinterest-for-woocommerce' ) );
			}

			foreach ( $feeds['data'] as $feed_profile ) {

				if ( $feed_id === $feed_profile->id ) {
					return $feed_profile;
				}
			}

			// No feed found.
			throw new \Exception( esc_html__( 'No feed found with the requested ID.', 'pinterest-for-woocommerce' ) );

		} catch ( \Exception $e ) {

			Logger::log( $e->getMessage(), 'error' );

			throw $e;
		}
	}


	/**
	 * Get merchant's feed based on feed location
	 *
	 * @param string $merchant_id   The merchant ID.
	 * @param string $feed_location The feed full location.
	 *
	 * @return mixed
	 *
	 * @throws \Exception PHP Exception.
	 */
	public static function get_merchant_feed_by_location( $merchant_id, $feed_location ) {
		try {

			$feeds = API\Base::get_merchant_feeds( $merchant_id );

			if ( 'success' !== $feeds['status'] ) {
				throw new \Exception( esc_html__( 'Could not get feed info.', 'pinterest-for-woocommerce' ) );
			}

			if ( ! is_array( $feeds['data'] ) ) {
				throw new \Exception( esc_html__( 'Wrong feed info.', 'pinterest-for-woocommerce' ) );
			}

			foreach ( $feeds['data'] as $feed_profile ) {

				if ( $feed_location === $feed_profile->location_config->full_feed_fetch_location ) {
					return $feed_profile;
				}
			}

			// No feed found.
			throw new \Exception( esc_html__( 'No feed found with the requested location.', 'pinterest-for-woocommerce' ) );

		} catch ( \Exception $e ) {

			Logger::log( $e->getMessage(), 'error' );

			throw $e;
		}
	}

}
