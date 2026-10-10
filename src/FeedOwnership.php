<?php
/**
 * Pinterest for WooCommerce record of the feeds this plugin owns on Pinterest.
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     x.x.x
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest;

use Pinterest_For_Woocommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps the IDs of the Pinterest catalog feeds this plugin created or registered to.
 *
 * Feed ownership used to be inferred from the feed location alone, which fails for feeds
 * created before a domain, uploads path or CDN change. The record is keyed by ad account
 * because feed IDs only make sense within the ad account they were created under.
 *
 * The record is stored in its own option so that it survives disconnecting the account:
 * a feed Pinterest refused to delete on disconnect stays known to the plugin after a
 * reconnect, when the location heuristic can no longer identify it.
 *
 * @class   FeedOwnership
 * @version x.x.x
 */
class FeedOwnership {

	/**
	 * Option holding the record: ad account ID => array( feed ID => recorded Unix timestamp ).
	 */
	const OPTION_NAME = PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME . '_owned_feeds';

	/**
	 * Records a feed as owned by this plugin.
	 *
	 * Idempotent: a feed already on record keeps its original timestamp.
	 *
	 * @since x.x.x
	 *
	 * @param string $feed_id       Pinterest feed ID.
	 * @param string $ad_account_id Pinterest ad account ID. Defaults to the connected one.
	 * @return void
	 */
	public static function record( string $feed_id, string $ad_account_id = '' ): void {
		$ad_account_id = self::resolve_ad_account_id( $ad_account_id );
		if ( '' === $feed_id || '' === $ad_account_id ) {
			return;
		}

		$owned = self::get_option();
		if ( isset( $owned[ $ad_account_id ][ $feed_id ] ) ) {
			return;
		}

		$owned[ $ad_account_id ][ $feed_id ] = time();
		update_option( self::OPTION_NAME, $owned, false );
	}

	/**
	 * Removes a feed from the record, for example after Pinterest confirmed its deletion.
	 *
	 * @since x.x.x
	 *
	 * @param string $feed_id       Pinterest feed ID.
	 * @param string $ad_account_id Pinterest ad account ID. Defaults to the connected one.
	 * @return void
	 */
	public static function forget( string $feed_id, string $ad_account_id = '' ): void {
		$ad_account_id = self::resolve_ad_account_id( $ad_account_id );
		$owned         = self::get_option();
		if ( ! isset( $owned[ $ad_account_id ][ $feed_id ] ) ) {
			return;
		}

		unset( $owned[ $ad_account_id ][ $feed_id ] );
		if ( empty( $owned[ $ad_account_id ] ) ) {
			unset( $owned[ $ad_account_id ] );
		}
		update_option( self::OPTION_NAME, $owned, false );
	}

	/**
	 * Tells whether a feed is on record as owned by this plugin.
	 *
	 * @since x.x.x
	 *
	 * @param string $feed_id       Pinterest feed ID.
	 * @param string $ad_account_id Pinterest ad account ID. Defaults to the connected one.
	 * @return bool
	 */
	public static function is_recorded( string $feed_id, string $ad_account_id = '' ): bool {
		return isset( self::get_recorded( $ad_account_id )[ $feed_id ] );
	}

	/**
	 * Returns the recorded feeds of an ad account.
	 *
	 * @since x.x.x
	 *
	 * @param string $ad_account_id Pinterest ad account ID. Defaults to the connected one.
	 * @return array<string, int> Feed ID => Unix timestamp of when the feed was recorded.
	 */
	public static function get_recorded( string $ad_account_id = '' ): array {
		$ad_account_id = self::resolve_ad_account_id( $ad_account_id );
		$recorded      = self::get_option()[ $ad_account_id ] ?? array();

		return is_array( $recorded ) ? $recorded : array();
	}

	/**
	 * Falls back to the connected ad account when none is given.
	 *
	 * @param string $ad_account_id Pinterest ad account ID or an empty string.
	 * @return string
	 */
	private static function resolve_ad_account_id( string $ad_account_id ): string {
		if ( '' !== $ad_account_id ) {
			return $ad_account_id;
		}

		return (string) Pinterest_For_Woocommerce()::get_setting( 'tracking_advertiser' );
	}

	/**
	 * Reads the record, tolerating a missing or corrupted option value.
	 *
	 * @return array
	 */
	private static function get_option(): array {
		$owned = get_option( self::OPTION_NAME, array() );

		return is_array( $owned ) ? $owned : array();
	}
}
