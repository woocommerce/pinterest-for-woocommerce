<?php
/**
 * Pinterest for WooCommerce Sync Settings
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     x.x.x
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to handle PinterestSyncSettings.
 */
class PinterestSyncSettings {

	const SYNCED_SETTINGS = array();

	/**
	 * Get the list of synced settings.
	 *
	 * @return array
	 */
	public static function get_synced_settings() {

		return self::SYNCED_SETTINGS;
	}


	/**
	 * Sync settings.
	 */
	public static function sync_settings() {

		foreach ( self::SYNCED_SETTINGS as $setting ) {

			if ( ! self::sync_setting( $setting ) ) {
				return false;
			}
		}

		return true;
	}


	/**
	 * Sync setting.
	 *
	 * @param  string $setting Setting name.
	 *
	 * @return bool
	 */
	public static function sync_setting( $setting ) {

		if ( ! in_array( $setting, self::SYNCED_SETTINGS, true ) ) {
			return false;
		}

		if ( ! is_callable( array( __CLASS__, $setting ) ) ) {
			return false;
		}

		return call_user_func( array( __CLASS__, $setting ) );
	}

}
