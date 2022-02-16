<?php
/**
 * Include Pinterest data in the WC Tracker snapshot.
 *
 * @package Automattic\WooCommerce\Pinterest
 */

namespace Automattic\WooCommerce\Pinterest;

use Pinterest_For_Woocommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class handling Woo Tracker
 */
class TrackerSnapshot {


	/**
	 * Not needed if allow_tracking is disabled.
	 *
	 * @return bool Whether the object is needed.
	 */
	public static function is_needed(): bool {
		return 'yes' === get_option( 'woocommerce_allow_tracking', 'no' );
	}

	/**
	 * Hook extension tracker data into the WC tracker data.
	 */
	public static function maybe_init(): void {

		if ( ! self::is_needed() ) {
			return;
		}

		add_filter(
			'woocommerce_tracker_data',
			function ( $data ) {
				return self::include_snapshot_data( $data );
			}
		);
	}

	/**
	 * Add extension data to the WC Tracker snapshot.
	 *
	 * @param array $data The existing array of tracker data.
	 *
	 * @return array The updated array of tracker data.
	 */
	protected static function include_snapshot_data( array $data = array() ): array {
		if ( ! isset( $data['extensions'] ) ) {
			$data['extensions'] = array();
		}

		$data['extensions'][ PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX ] = array(
			'settings' => self::parse_settings(),
		);

		return $data;
	}

	/**
	 * Parse general extension and settings data in the required format.
	 *
	 * @return array
	 */
	protected static function parse_settings(): array {

		$settings = Pinterest_For_Woocommerce::get_settings( true );

		/**
		 * Lambda function to parse booleans into strings
		 *
		 * @param $key
		 * @return string
		 */
		$fn_boolean_setting_to_string = function ( $key ) use ( $settings ): string {
			return $settings[ $key ] ? 'yes' : 'no';
		};

		// options to be formatted as "yes" or "no".
		$boolean_options = array(
			'track_conversions',
			'enhanced_match_support',
			'save_to_pinterest',
			'rich_pins_on_posts',
			'rich_pins_on_products',
			'product_sync_enabled',
			'enable_debug_logging',
			'erase_plugin_data',
		);

		return array_merge(
			array( 'version' => PINTEREST_FOR_WOOCOMMERCE_VERSION ),
			array_combine( $boolean_options, array_map( $fn_boolean_setting_to_string, $boolean_options ) )
		);
	}
}
