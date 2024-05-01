<?php
/**
 * Pinterest for WooCommerce Sync Settings
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.2.18
 */

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Pinterest\API\APIV5;
use Exception;
use DateTime;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to handle PinterestSyncSettings.
 */
class PinterestSyncSettings {

	const SYNCED_SETTINGS = array(
		'automatic_enhanced_match_support',
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

		$formatted_synced_time = new DateTime();
		$formatted_synced_time = $formatted_synced_time->format( 'j M Y, h:i:s a' );

		Pinterest_For_Woocommerce()::save_setting( 'last_synced_settings', $formatted_synced_time );

		$synced_settings['last_synced_settings'] = $formatted_synced_time;

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


	/**
	 * Sync enhanced match support.
	 *
	 * @return bool
	 *
	 * @throws Exception PHP Exception.
	 */
	private static function automatic_enhanced_match_support() {
		/*
		 * Tracking needs to be enabled in order to use automatic enhanced match support.
		 */
		$is_tracking_enabled = Pinterest_For_Woocommerce()::get_setting( 'track_conversions' );
		if ( ! $is_tracking_enabled ) {
			return false;
		}

		try {
			$advertiser_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
			$tag_id        = Pinterest_For_WooCommerce()::get_setting( 'tracking_tag' );

			if ( ! $advertiser_id || ! $tag_id ) {
				throw new Exception( esc_html__( 'Tracking advertiser or tag missing', 'pinterest-for-woocommerce' ), 400 );
			}

			$response = APIV5::get_advertiser_tag( $advertiser_id, $tag_id );
			$automatic_enhanced_match_support = $response['configs']['aem_enabled'] ?? false;
			Pinterest_For_Woocommerce()::save_setting( 'automatic_enhanced_match_support', $automatic_enhanced_match_support );
		} catch ( Exception $th ) {
			Logger::log( $th->getMessage(), 'error' );
			throw new Exception( esc_html__( 'Response error', 'pinterest-for-woocommerce' ), 400 );
		}

		return Pinterest_For_Woocommerce()::get_setting( 'automatic_enhanced_match_support' );
	}
}
