<?php
/**
 * Helper class for managing the settings.
 *
 * @package Automattic\WooCommerce\Pinterest
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 */
class Settings {

	/**
	 * When set to true, the settings have been
	 * changed and the runtime cached must be flushed
	 *
	 * @var Pinterest_For_Woocommerce
	 * @since 1.0.0
	 */
	protected static $dirty_settings = array();

	/**
	 * The default settings that will be created
	 * with the given values, if they don't exist.
	 *
	 * @var Pinterest_For_Woocommerce
	 * @since 1.0.0
	 */
	protected static $default_settings = array(
		'track_conversions'      => true,
		'enhanced_match_support' => true,
		'save_to_pinterest'      => true,
		'rich_pins_on_posts'     => true,
		'rich_pins_on_products'  => true,
		'product_sync_enabled'   => true,
		'enable_debug_logging'   => false,
		'erase_plugin_data'      => false,
	);

	/**
	 * Initiate class.
	 */
	public static function maybe_init() {
		add_action( 'pinterest_for_woocommerce_token_saved', array( __CLASS__, 'set_default_settings' ) );

		// Allow access to our option through the REST API.
		add_filter( 'woocommerce_rest_api_option_permissions', array( __CLASS__, 'add_option_permissions' ), 10, 1 );

		// Disconnect advertiser if advertiser or tag change.
		add_action( 'update_option_pinterest_for_woocommerce', array( __CLASS__, 'settings_update' ), 10, 2 );
	}

	/**
	 * Return APP Settings
	 *
	 * @since 1.0.0
	 *
	 * @param boolean $force  Controls whether to force getting a fresh value instead of one from the runtime cache.
	 * @param string  $option Controls which option to read/write to.
	 *
	 * @return array
	 */
	public static function get_settings( $force = false, $option = PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME ) {

		static $settings;

		if ( $force || is_null( $settings ) || ! isset( $settings[ $option ] ) || ( isset( self::$dirty_settings[ $option ] ) && self::$dirty_settings[ $option ] ) ) {
			$settings[ $option ] = get_option( $option );
		}

		return $settings[ $option ];
	}

	/**
	 * Return APP Setting based on its key
	 *
	 * @since 1.0.0
	 *
	 * @param string  $key The key of specific option to retrieve.
	 * @param boolean $force Controls whether to force getting a fresh value instead of one from the runtime cache.
	 *
	 * @return mixed
	 */
	public static function get_setting( $key, $force = false ) {

		$settings = self::get_settings( $force );

		return empty( $settings[ $key ] ) ? false : $settings[ $key ];
	}

	/**
	 * Save APP Setting
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The key of specific option to retrieve.
	 * @param mixed  $data The data to save for this option key.
	 *
	 * @return boolean
	 */
	public static function save_setting( $key, $data ) {

		$settings = self::get_settings( true );

		$settings[ $key ] = $data;

		return self::save_settings( $settings );
	}

	/**
	 * Save APP Settings
	 *
	 * @since 1.0.0
	 *
	 * @param array  $settings The array of settings to save.
	 * @param string $option Controls which option to read/write to.
	 *
	 * @return boolean
	 */
	public static function save_settings( $settings, $option = PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME ) {
		self::$dirty_settings[ $option ] = true;
		return update_option( $option, $settings );
	}

	/**
	 * Sets the default settings based on the
	 * given values in self::$default_settings
	 *
	 * @return boolean
	 */
	public static function set_default_settings() {

		$settings = self::get_settings( true );
		$settings = wp_parse_args( $settings, self::$default_settings );

		return self::save_settings( $settings );
	}

	/**
	 * Allow access to our option through the REST API for a user that can manage the store.
	 * The UI relies on this option being available through the API.
	 *
	 * @param array $permissions The permissions array.
	 *
	 * @return array
	 */
	public static function add_option_permissions( $permissions ) {

		$permissions[ PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME ] = current_user_can( 'manage_woocommerce' );
		return $permissions;
	}

	/**
	 * Triggered when the settings are updated through the API.
	 *
	 * @param array $old_value The old value of the option.
	 * @param array $new_value The new value of the option.
	 */
	public static function settings_update( $old_value, $new_value ) {
		if ( ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return;
		}

		self::maybe_disconnect_advertiser( $old_value, $new_value );
		self::maybe_update_tag( $old_value, $new_value );
	}

	/**
	 * Disconnect advertiser from the platform if advertiser or tag change.
	 *
	 * @param array $old_value The old value of the option.
	 * @param array $new_value The new value of the option.
	 */
	private static function maybe_disconnect_advertiser( $old_value, $new_value ) {
		if (
			! isset( $old_value['tracking_advertiser'] ) ||
			! isset( $old_value['tracking_tag'] ) ||
			! isset( $new_value['tracking_advertiser'] ) ||
			! isset( $new_value['tracking_tag'] )
		) {
			return;
		}

		// Disconnect merchant if old values are different than new ones.
		if ( $old_value['tracking_advertiser'] !== $new_value['tracking_advertiser'] || $old_value['tracking_tag'] !== $new_value['tracking_tag'] ) {

			try {

				API\AdvertiserConnect::disconnect_advertiser( $old_value['tracking_advertiser'], $old_value['tracking_tag'] );

			} catch ( \Exception $th ) {

				Logger::log( esc_html__( 'There was an error disconnecting the Advertiser. Please try again.', 'pinterest-for-woocommerce' ) );
			}
		}
	}

	/**
	 * Disconnect advertiser from the platform if advertiser or tag change.
	 * Only update if tag is being enabled.
	 *
	 * @param array $old_value The old value of the option.
	 * @param array $new_value The new value of the option.
	 */
	private static function maybe_update_tag( $old_value, $new_value ) {
		$connected_tag = self::get_setting( 'tracking_tag' );

		if (
			! $connected_tag ||
			! isset( $new_value['enhanced_match_support'] ) ||
			! $new_value['enhanced_match_support'] ||
			(
				isset( $old_value['enhanced_match_support'] ) &&
				$new_value['enhanced_match_support'] === $old_value['enhanced_match_support']
			)
		) {
			return;
		}

		try {
			API\Base::update_tag(
				$connected_tag,
				array(
					'aem_enabled' => true,
				)
			);
		} catch ( \Exception $th ) {
			Logger::log( esc_html__( 'There was an error updating the tag.', 'pinterest-for-woocommerce' ) );
		}
	}

}
