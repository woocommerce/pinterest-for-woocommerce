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
use Automattic\WooCommerce\Pinterest\API\Base;
use Automattic\WooCommerce\Pinterest\Exception\PinterestApiLocaleException;
use Exception;
use Throwable;

/**
 * Class handling fetch methods for feed profiles.
 */
class Feeds {

	const FEED_STATUS_ACTIVE = 'ACTIVE';

	const FEED_STATUS_INACTIVE = 'INACTIVE';

	/**
	 * Create a new feed for the given ad account.
	 *
	 * @since x.x.x
	 *
	 * @return string The Feed ID or an empty string if failed.
	 * @throws Exception PHP Exception if there is an error creating the feed, and we are throttling the requests.
	 */
	public static function create_feed(): string {
		$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
		$configs       = LocalFeedConfigs::get_instance()->get_configurations();
		$config        = reset( $configs );

		$default_country  = Pinterest_For_Woocommerce()::get_base_country() ?? 'US';
		$default_currency = get_woocommerce_currency();

		/**
		 * Filters the default feed name: pinterest_for_woocommerce_unique_feed_name.
		 * This vale appears in the Catalogues - Data sources page at Pinterest.
		 *
		 * @param string $feed_name The default feed name.
		 */
		$feed_name = apply_filters(
			'pinterest_for_woocommerce_unique_feed_name',
			esc_html__(
				'Created by Pinterest for WooCommerce ' . $default_country . '-' . $default_currency,
				'pinterest-for-woocommerce'
			)
		);

		$data = array(
			'name'                          => $feed_name,
			'format'                        => 'XML',
			'location'                      => $config['feed_url'],
			'catalog_type'                  => 'RETAIL',
			'default_currency'              => $default_currency,
			'default_locale'                => LocaleMapper::get_locale_for_api(),
			'default_country'               => $default_country,
			'default_availability'          => 'IN_STOCK',
		);

		$cache_key = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_request_' . md5( wp_json_encode( $data ) );
		$cache     = get_transient( $cache_key );

		if ( false !== $cache ) {
			throw new Exception(
				__( 'There was a previous error trying to create a feed.', 'pinterest-for-woocommerce' ),
				(int) $cache
			);
		}

		try {
			$feed = APIV5::create_feed( $data, $ad_account_id );
		} catch ( Throwable $th ) {
			$delay = Pinterest_For_Woocommerce()::get_data( 'create_feed_delay' ) ?? MINUTE_IN_SECONDS;
			set_transient( $cache_key, $th->getCode(), $delay );
			// Double the delay.
			Pinterest_For_Woocommerce()::save_data(
				'create_feed_delay',
				min( $delay * 2, 6 * HOUR_IN_SECONDS )
			);
			throw new Exception( $th->getMessage(), $th->getCode() );
		}

		static::invalidate_feeds_cache();

		try {
			$feed_id = static::match_local_feed_configuration_to_registered_feeds( array( $feed ) );
		} catch ( Throwable $th ) {
			$feed_id = '';
		}

		// Clean the cached delay.
		Pinterest_For_Woocommerce()::save_data( 'create_feed_delay', false );

		return $feed_id;
	}

	/**
	 * Get a specific merchant feed using the given arguments.
	 *
	 * @param string $feed_id     The ID of the feed.
	 *
	 * @return object The feed profile object.
	 *
	 * @throws Exception PHP Exception.
	 */
	public static function get_feed( $feed_id ) {
		try {
			$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
			$feeds         = APIV5::get_feeds( $ad_account_id );
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
	 * @throws PinterestApiException Pinterest API Exception.
	 */
	public static function get_feeds(): array {
		try {
			$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
			$feeds         = APIV5::get_feeds( $ad_account_id );
			return $feeds['items'] ?? [];
		} catch ( PinterestApiException $e ) {
			Logger::log( $e->getMessage(), 'error' );
			return [];
		}
	}

	/**
	 * Invalidate the merchant feeds cache.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public static function invalidate_feeds_cache() {
		$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
		APIV5::invalidate_feeds_cache( $ad_account_id );
	}

	/**
	 * Verify if the local feed is already registered to the merchant.
	 * Return its ID if it is.
	 *
	 * @param array $feeds The list of feeds to check against. If not set, the list will be fetched from the API.
	 *
	 * @return string Returns the ID of the feed if properly registered or an empty string otherwise.
	 * @throws PinterestApiException Pinterest API Exception.
	 * @throws PinterestApiLocaleException No valid locale found to check for the registered feed.
	 */
	public static function match_local_feed_configuration_to_registered_feeds( array $feeds = array() ): string {
		$configs       = LocalFeedConfigs::get_instance()->get_configurations();
		$config        = reset( $configs );
		$local_path    = $config['feed_url'];
		$local_country = Pinterest_For_Woocommerce()::get_base_country() ?? 'US';
		$local_locale  = LocaleMapper::get_locale_for_api();

		if ( empty( $feeds ) ) {
			$feeds = self::get_feeds();
		}

		foreach ( $feeds as $feed ) {
			$does_match = $local_path === $feed['location'];
			$does_match = $does_match && $local_country === $feed['default_country'];
			$does_match = $does_match && $local_locale === $feed['default_locale'];
			if ( $does_match ) {
				// We can assume we're on the same site.
				return $feed['id'];
			}
		}

		return '';
	}

	/**
	 * Check if the registered feed is enabled.
	 *
	 * @since 1.2.13
	 *
	 * @param string $feed_profile_id The ID of the feed.
	 *
	 * @return bool True if the feed is active, false otherwise.
	 */
	public static function is_local_feed_enabled( $feed_profile_id ) {
		$feed = self::get_feed( $feed_profile_id );
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
			APIV5::enable_feed( $ad_account_id, $feed_id );
			// We don't need to check the status, lets just invalidate the cache for extra safety.
			self::invalidate_feeds_cache();
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
			APIV5::disable_feed( $ad_account_id, $feed_id );
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
	 * Get the latest report of the active feed related to the last attempt to process and ingest our feed.
	 *
	 * @param string $feed_id       Pinterest feed ID.
	 * @param string $ad_account_id Pinterest Ad Account ID.
	 *
	 * @return array The feed ingestion and processing report or null.
	 */
	public static function get_feed_processing_results( $feed_id, $ad_account_id ): array {
		$feed_report   = APIV5::get_feed_processing_results( $feed_id, $ad_account_id );
		if ( empty( $feed_report ) ) {
			return array();
		}
		return $feed_report;
	}
}
