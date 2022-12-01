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

use Automattic\WooCommerce\Pinterest\API\Base;
use \Exception;

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
	 * Get merchant's feeds
	 *
	 * @param string $merchant_id The merchant ID.
	 *
	 * @return array The feed profile objects.
	 *
	 * @throws Exception PHP Exception.
	 */
	public static function get_merchant_feeds( $merchant_id ) {

		try {

			$feeds = API\Base::get_merchant_feeds( $merchant_id );

			if ( 'success' !== $feeds['status'] ) {
				throw new Exception( esc_html__( 'Could not get feed info.', 'pinterest-for-woocommerce' ) );
			}

			if ( ! is_array( $feeds['data'] ) ) {
				throw new Exception( esc_html__( 'Wrong feed info.', 'pinterest-for-woocommerce' ) );
			}

			return $feeds['data'];

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
		$configs       = LocalFeedConfigs::get_instance()->get_configurations();
		$config        = reset( $configs );
		$local_path    = dirname( $config['feed_url'] );
		$local_country = Pinterest_For_Woocommerce()::get_base_country() ?? 'US';
		$local_locale  = str_replace( '_', '-', determine_locale() );
		$feeds         = self::get_merchant_feeds( $merchant_id );

		foreach ( $feeds as $feed ) {
			$configured_path = dirname( $feed->location_config->full_feed_fetch_location );
			if (
				$configured_path === $local_path &&
				$local_country === $feed->country &&
				$local_locale === $feed->locale
			) {
				// We can assume we're on the same site.
				return $feed->id;
			}
		}

		return '';
	}

}
