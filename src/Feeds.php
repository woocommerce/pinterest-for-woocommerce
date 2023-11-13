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

use Automattic\WooCommerce\Pinterest\API\APIV5;
use \Exception;
use Automattic\WooCommerce\Pinterest\API\Base;
use Automattic\WooCommerce\Pinterest\Exception\PinterestApiLocaleException;
use Throwable;

/**
 * Class handling fetch methods for feed profiles.
 */
class Feeds {

	const FEED_STATUS_ACTIVE = 'ACTIVE';

	const FEED_STATUS_INACTIVE = 'INACTIVE';

	/**
	 * Get a specific merchant feed using the given arguments.
	 *
	 * @param string $feed_id     The ID of the feed.
	 *
	 * @return object The feed profile object.
	 *
	 * @throws Exception PHP Exception.
	 */
	public static function get_ad_account_feed( $feed_id ) {
		try {
			$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
			$feeds         = APIV5::get_ad_account_feeds( $ad_account_id );
			foreach ( $feeds['items'] as $feed ) {
				// Get the feed with the requested id if exists.
				if ( $feed_id === $feed['id'] ) {
					return $feed;
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
	 * Get merchant's feeds.
	 *
	 * @return array The feed profile objects.
	 *
	 * @throws Exception PHP Exception.
	 */
	public static function get_ad_account_feeds() {
		try {
			$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
			$feeds         = APIV5::get_ad_account_feeds( $ad_account_id );
			return $feeds['items'] ?? [];
		} catch ( Exception $e ) {
			Logger::log( $e->getMessage(), 'error' );
			throw $e;
		}
	}

	/**
	 * Invalidate the merchant feeds cache.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public static function invalidate_get_ad_account_feeds_cache() {
		$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
		APIV5::invalidate_ad_account_feeds_cache( $ad_account_id );
	}

	/**
	 * Verify if the local feed is already registered to the merchant.
	 * Return its ID if it is.
	 *
	 * @param string $merchant_id The merchant ID.
	 *
	 * @throws PinterestApiLocaleException No valid locale found to check for the registered feed.
	 * @return string Returns the ID of the feed if properly registered or an empty string otherwise.
	 */
	public static function match_local_feed_configuration_to_registered_feeds( $merchant_id ) {
		$configs       = LocalFeedConfigs::get_instance()->get_configurations();
		$config        = reset( $configs );
		$local_path    = $config['feed_url'];
		$local_country = Pinterest_For_Woocommerce()::get_base_country() ?? 'US';
		$local_locale  = LocaleMapper::get_locale_for_api();
		$feeds         = self::get_ad_account_feeds();

		foreach ( $feeds as $feed ) {
			$configured_path = $feed->location_config->full_feed_fetch_location;
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

	/**
	 * Check if the registered feed is enabled.
	 *
	 * @since 1.2.13
	 *
	 * @param string $merchant_id     The merchant ID.
	 * @param string $feed_profile_id The ID of the feed.
	 *
	 * @return bool True if the feed is active, false otherwise.
	 */
	public static function is_local_feed_enabled( $merchant_id, $feed_profile_id ) {
		$feed = self::get_ad_account_feed( $feed_profile_id );
		return 'ACTIVE' === $feed->feed_status;
	}

	/**
	 * Enabled the feed.
	 *
	 * @since x.x.x
	 *
	 * @param string $feed_id The ID of the feed.
	 *
	 * @return bool True if the feed is has been enabled, false otherwise.
	 */
	public static function enabled_feed( $feed_id ) {
		try {
			$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
			APIV5::enable_merchant_feed( $ad_account_id, $feed_id );
			// We don't need to check the status, lets just invalidate the cache for extra safety.
			self::invalidate_get_ad_account_feeds_cache();
			return true;
		} catch ( Throwable $th ) {
			Logger::log( $th->getMessage(), 'error' );
			return false;
		}
	}

	/**
	 * Enabled the feed.
	 *
	 * @since x.x.x
	 *
	 * @param string $feed_id The ID of the feed.
	 *
	 * @return bool True if the feed is has been disabled, false otherwise.
	 */
	public static function disable_feed( $feed_id ) {
		try {
			$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
			APIV5::disable_merchant_feed( $ad_account_id, $feed_id );
			return true;
		} catch ( Throwable $th ) {
			Logger::log( $th->getMessage(), 'error' );
			return false;
		}
	}

	/**
	 * Get the latest Workflow of the active feed related to the last attempt to process and ingest our feed.
	 *
	 * @param string $merchant_id The merchant ID.
	 * @param string $feed_id     The ID of the feed.
	 *
	 * @return object|null The latest workflow object or null if there is no workflow.
	 *
	 * @throws Exception If there is an error getting the feed report.
	 *
	 * @since 1.3.0
	 */
	public static function get_feed_latest_workflow( string $merchant_id, string $feed_id ): ?object {
		$feed_report = Base::get_merchant_feed_report( $merchant_id, $feed_id );
		if ( ! $feed_report || 'success' !== $feed_report['status'] ) {
			throw new Exception( esc_html__( 'Could not get feed report from Pinterest.', 'pinterest-for-woocommerce' ), 400 );
		}
		if ( ! property_exists( $feed_report['data'], 'workflows' ) || ! is_array( $feed_report['data']->workflows ) || empty( $feed_report['data']->workflows ) ) {
			return null;
		}

		usort(
			$feed_report['data']->workflows,
			function ( $a, $b ) {
				return $b->created_at - $a->created_at;
			}
		);

		return reset( $feed_report['data']->workflows );
	}

	/**
	 * Get the latest Workflow of the active feed related to the last attempt to process and ingest our feed.
	 *
	 * @param string $feed_id       Pinterest feed ID.
	 * @param string $ad_account_id Pinterest ad account ID.
	 * @param string $bookmark      Bookmark to fetch results from.
	 * @param int    $per_page      Number of results to fetch per page.
	 *
	 * @return void
	 */
	public static function get_feed_processing_results( $feed_id, $ad_account_id, $bookmark = '', $per_page = 25 ) {

	}
}
