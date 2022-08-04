<?php
/**
 * Pinterest for WooCommerce Sync Settings
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     x.x.x
 */

namespace Automattic\WooCommerce\Pinterest;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to handle PinterestSyncSettings.
 */
class PinterestSyncSettings {

	const SYNCED_SETTINGS = array(
		'enhanced_match_support',
	);

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

		$synced_settings = array();

		try {
			foreach ( self::SYNCED_SETTINGS as $setting ) {
				$synced_settings[ $setting ] = self::sync_setting( $setting );
			}
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}

		return array(
			'success'         => true,
			'synced_settings' => $synced_settings,
		);
	}


	/**
	 * Sync setting.
	 *
	 * @param  string $setting Setting name.
	 *
	 * @return mixed
	 *
	 * @throws Exception PHP Exception.
	 */
	private static function sync_setting( $setting ) {

		if ( ! is_callable( array( __CLASS__, $setting ) ) ) {
			throw new Exception( esc_html__( 'Missing method to sync the setting.', 'pinterest-for-woocommerce' ) );
		}

		return call_user_func( array( __CLASS__, $setting ) );
	}

}
