<?php
/**
 * Pinterest for WooCommerce Ratings Notice Eligibility.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @since   x.x.x
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\RatingsNotice;

use Automattic\WooCommerce\Pinterest\ProductFeedStatus;
use Automattic\WooCommerce\Pinterest\Utilities\Utilities;
use Pinterest_For_Woocommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Computes whether a merchant is eligible to see the in-plugin ratings notice.
 *
 * Pure read-only evaluation of the 5-layer gate described in the criteria plan:
 * hard eligibility, active usage (2 of 3), happiness proxies, anti-signals.
 * Fatigue/frequency and state transitions live in {@see State}.
 */
class Eligibility {

	const MIN_CONNECTED_DAYS    = 14;
	const MIN_PRODUCTS_SYNCED   = 5;
	const SYNC_RECENCY_DAYS     = 7;
	const RECENT_RECONNECT_DAYS = 14;
	const MIN_STORE_PRODUCTS    = 3;
	const ORDER_LOOKBACK_DAYS   = 60;

	const REASON_OK                 = 'ok';
	const REASON_SETUP_INCOMPLETE   = 'setup_incomplete';
	const REASON_TOO_NEW            = 'too_new';
	const REASON_STAGING            = 'staging';
	const REASON_SYNC_FAILING       = 'sync_failing';
	const REASON_RECENT_RECONNECT   = 'recent_reconnect';
	const REASON_INSUFFICIENT_USAGE = 'insufficient_usage';
	const REASON_STORE_NOT_REAL     = 'store_not_real';

	/**
	 * Evaluate all gates and return a structured result.
	 *
	 * @return array{eligible: bool, reason: string}
	 */
	public static function compute(): array {
		$result = self::evaluate();

		/**
		 * Filters the computed ratings notice eligibility result.
		 *
		 * @since x.x.x
		 *
		 * @param array $result Array with keys `eligible` (bool) and `reason` (string).
		 */
		return apply_filters( 'pinterest_for_woocommerce_rating_notice_eligibility', $result );
	}

	/**
	 * Internal evaluation without the final filter.
	 *
	 * @return array{eligible: bool, reason: string}
	 */
	private static function evaluate(): array {
		if ( ! Pinterest_For_Woocommerce::is_setup_complete() || ! Pinterest_For_Woocommerce::is_business_connected() ) {
			return self::ineligible( self::REASON_SETUP_INCOMPLETE );
		}

		if ( ! self::connected_for_at_least( self::MIN_CONNECTED_DAYS ) ) {
			return self::ineligible( self::REASON_TOO_NEW );
		}

		if ( self::is_non_production() ) {
			return self::ineligible( self::REASON_STAGING );
		}

		if ( self::has_recent_sync_error() ) {
			return self::ineligible( self::REASON_SYNC_FAILING );
		}

		if ( self::recently_reconnected() ) {
			return self::ineligible( self::REASON_RECENT_RECONNECT );
		}

		$usage_score = (int) self::catalog_sync_live()
			+ (int) self::conversion_tracking_active()
			+ (int) self::token_refreshed_post_install();

		if ( $usage_score < 2 ) {
			return self::ineligible( self::REASON_INSUFFICIENT_USAGE );
		}

		if ( ! self::store_is_real() ) {
			return self::ineligible( self::REASON_STORE_NOT_REAL );
		}

		return self::eligible();
	}

	/**
	 * Has the account been connected for at least the given number of days?
	 *
	 * @param int $days Minimum days connected.
	 * @return bool
	 */
	public static function connected_for_at_least( int $days ): bool {
		$ts = Utilities::get_account_connection_timestamp();
		if ( $ts <= 0 ) {
			return false;
		}
		return ( time() - $ts ) >= ( $days * DAY_IN_SECONDS );
	}

	/**
	 * True when the site is not a production environment.
	 *
	 * @return bool
	 */
	public static function is_non_production(): bool {
		return function_exists( 'wp_get_environment_type' )
			&& 'production' !== wp_get_environment_type();
	}

	/**
	 * True when the feed is in an error state or has an error message set.
	 *
	 * @return bool
	 */
	public static function has_recent_sync_error(): bool {
		$state = ProductFeedStatus::get();
		if ( 'error' === ( $state['status'] ?? '' ) ) {
			return true;
		}
		return ! empty( $state['error_message'] );
	}

	/**
	 * True when the account was (re)connected inside the recent-reconnect window.
	 *
	 * @return bool
	 */
	public static function recently_reconnected(): bool {
		$ts = Utilities::get_account_connection_timestamp();
		if ( $ts <= 0 ) {
			return false;
		}
		return ( time() - $ts ) < ( self::RECENT_RECONNECT_DAYS * DAY_IN_SECONDS );
	}

	/**
	 * Catalog sync has generated recently with a non-trivial product count.
	 *
	 * @return bool
	 */
	public static function catalog_sync_live(): bool {
		$state         = ProductFeedStatus::get();
		$last_activity = (int) ( $state['last_activity'] ?? 0 );
		$product_count = (int) ( $state['product_count'] ?? 0 );
		$status        = $state['status'] ?? '';

		if ( 'generated' !== $status ) {
			return false;
		}
		if ( $product_count < self::MIN_PRODUCTS_SYNCED ) {
			return false;
		}
		if ( $last_activity <= 0 ) {
			return false;
		}
		return ( time() - $last_activity ) <= ( self::SYNC_RECENCY_DAYS * DAY_IN_SECONDS );
	}

	/**
	 * Conversion tracking is enabled and configured.
	 *
	 * @return bool
	 */
	public static function conversion_tracking_active(): bool {
		$enabled = (bool) Pinterest_For_Woocommerce::get_setting( 'track_conversions' );
		if ( ! $enabled ) {
			return false;
		}
		$tag = Pinterest_For_Woocommerce::get_setting( 'tracking_tag' );
		return ! empty( $tag );
	}

	/**
	 * OAuth token has been refreshed at least once after initial connection.
	 *
	 * @return bool
	 */
	public static function token_refreshed_post_install(): bool {
		$token_data   = Pinterest_For_Woocommerce::get_data( 'token_data', true );
		$refresh_time = (int) ( is_array( $token_data ) ? ( $token_data['refresh_time'] ?? 0 ) : 0 );
		$connected_ts = Utilities::get_account_connection_timestamp();
		if ( $refresh_time <= 0 || $connected_ts <= 0 ) {
			return false;
		}
		return $refresh_time > $connected_ts;
	}

	/**
	 * Heuristic for "real store": enough products and at least one order recently.
	 *
	 * @return bool
	 */
	public static function store_is_real(): bool {
		$product_counts    = wp_count_posts( 'product' );
		$published_product = isset( $product_counts->publish ) ? (int) $product_counts->publish : 0;
		if ( $published_product < self::MIN_STORE_PRODUCTS ) {
			return false;
		}

		if ( ! function_exists( 'wc_get_orders' ) ) {
			return false;
		}

		$orders = wc_get_orders(
			array(
				'limit'        => 1,
				'return'       => 'ids',
				'date_created' => '>' . ( time() - self::ORDER_LOOKBACK_DAYS * DAY_IN_SECONDS ),
			)
		);
		return ! empty( $orders );
	}

	/**
	 * Build a positive eligibility result.
	 *
	 * @return array{eligible: true, reason: string}
	 */
	private static function eligible(): array {
		return array(
			'eligible' => true,
			'reason'   => self::REASON_OK,
		);
	}

	/**
	 * Build a negative eligibility result with the given reason code.
	 *
	 * @param string $reason One of the REASON_* constants.
	 * @return array{eligible: false, reason: string}
	 */
	private static function ineligible( string $reason ): array {
		return array(
			'eligible' => false,
			'reason'   => $reason,
		);
	}
}
