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

use \Exception;
use Automattic\WooCommerce\Pinterest\API\Base;
use Automattic\WooCommerce\Pinterest\Exception\PinterestApiLocaleException;

/**
 * Class handling fetch methods for feed profiles.
 */
class Feeds {

	/**
	 * Get a specific merchant feed using the given arguments.
	 *
	 * @param string $merchant_id The merchant ID the feed belongs to.
	 * @param string $feed_id     The ID of the feed.
	 *
	 * @return object The feed profile object.
	 *
	 * @throws Exception PHP Exception.
	 */
	public static function get_merchant_feed( $merchant_id, $feed_id ) {

		try {

			// Get the feeds of the merchant.
			$feeds = Base::get_merchant_feeds( $merchant_id );

			if ( 'success' !== $feeds['status'] ) {
				throw new Exception( esc_html__( 'Could not get feed info.', 'pinterest-for-woocommerce' ) );
			}

			if ( ! is_array( $feeds['data'] ) ) {
				throw new Exception( esc_html__( 'Wrong feed info.', 'pinterest-for-woocommerce' ) );
			}

			foreach ( $feeds['data'] as $feed_profile ) {

				// Get the feed with the requested id if exists.
				if ( $feed_id === $feed_profile->id ) {
					return $feed_profile;
				}
			}

			// No feed found.
			throw new Exception( esc_html__( 'No feed found with the requested ID.', 'pinterest-for-woocommerce' ) );

		} catch ( Exception $e ) {

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
	 * @return object The feed profile object.
	 *
	 * @throws Exception PHP Exception.
	 */
	public static function get_merchant_feed_by_location( $merchant_id, $feed_location ) {

		try {

			// Get the feeds of the merchant.
			$feeds = API\Base::get_merchant_feeds( $merchant_id );

			if ( 'success' !== $feeds['status'] ) {
				throw new Exception( esc_html__( 'Could not get feed info.', 'pinterest-for-woocommerce' ) );
			}

			if ( ! is_array( $feeds['data'] ) ) {
				throw new Exception( esc_html__( 'Wrong feed info.', 'pinterest-for-woocommerce' ) );
			}

			foreach ( $feeds['data'] as $feed_profile ) {

				// Get the feed with the requested location if exists.
				if ( $feed_location === $feed_profile->location_config->full_feed_fetch_location ) {
					return $feed_profile;
				}
			}

			// No feed found.
			throw new Exception( esc_html__( 'No feed found with the requested location.', 'pinterest-for-woocommerce' ) );

		} catch ( Exception $e ) {

			Logger::log( $e->getMessage(), 'error' );

			throw $e;
		}
	}


	/**
	 * Verify if the local feed is already registered to the merchant
	 *
	 * @param string $merchant_id The merchant ID.
	 *
	 * @return string Returns the ID of the feed if properly registered or an empty string otherwise.
	 */
	public static function is_local_feed_registered( $merchant_id ) {
		$configs = LocalFeedConfigs::get_instance()->get_configurations();
		$config  = reset( $configs );

		// We need to fetch the feed object using the local feed location.
		$feed = self::get_merchant_feed_by_location( $merchant_id, $config['feed_url'] );

		$configured_path = dirname( $feed->location_config->full_feed_fetch_location );
		$local_path      = dirname( $config['feed_url'] );
		$local_country   = Pinterest_For_Woocommerce()::get_base_country() ?? 'US';

		try {
			$local_locale = LocaleMapper::get_locale_for_api();
		} catch ( PinterestApiLocaleException $e ) {
			// Local feed locale is not supported by Pinterest.
			return '';
		}

		$registered_feed = '';

		if ( $configured_path === $local_path && $local_country === $feed->country && $local_locale === $feed->locale ) {

			// We can assume we're on the same site.
			$registered_feed = $feed->id;
		}

		return $registered_feed;
	}

}
