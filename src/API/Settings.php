<?php
/**
 * API Options
 *
 * @package     Pinterest_For_Woocommerce/API
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\API;

use Pinterest_For_Woocommerce;
use WP_Error;
use WP_REST_Server;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Endpoint handling settings updates.
 */
class Settings extends VendorAPI {

	/**
	 * Initialize class
	 */
	public function __construct() {

		$this->base                        = 'settings';
		$this->supports_multiple_endpoints = true;
		$this->endpoint_callbacks_map      = array(
			'get_settings' => WP_REST_Server::READABLE,
			'set_settings' => WP_REST_Server::CREATABLE,
		);

		$this->register_routes();
	}


	/**
	 * Handle get settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		Pinterest_For_Woocommerce()::maybe_check_billing_setup();
		$settings = Pinterest_For_Woocommerce()::get_settings( true );
		if ( empty( $settings['account_data']['id'] ) ) {
			$integration_data = Pinterest_For_Woocommerce::get_data( 'integration_data' );
			$settings['account_data']['id'] = $integration_data['connected_user_id'] ?? '';
		}
		return array(
			PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME => $settings,
		);
	}


	/**
	 * Handle set settings.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return array|WP_Error
	 */
	public function set_settings( WP_REST_Request $request ) {
		if ( ! $request->has_param( PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME ) || ! is_array( $request->get_param( PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME ) ) ) {
			return new WP_Error( \PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_options_error', esc_html__( 'Missing option parameters.', 'pinterest-for-woocommerce' ), array( 'status' => 400 ) );
		}

		$new_settings = $request->get_param( PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME );
		// Keep merchant-editable Pinterest_For_Woocommerce::$default_settings and tracking selections here.
		// Derived account data must remain server-owned.
		$editable_settings = array_fill_keys(
			array(
				'track_conversions',
				'track_conversions_capi',
				'enhanced_match_support',
				'automatic_enhanced_match_support',
				'save_to_pinterest',
				'rich_pins_on_posts',
				'rich_pins_on_products',
				'product_sync_enabled',
				'enable_debug_logging',
				'erase_plugin_data',
				'tracking_advertiser',
				'tracking_tag',
			),
			true
		);
		$current_settings  = Pinterest_For_Woocommerce()::get_settings( true );
		$current_settings  = is_array( $current_settings ) ? $current_settings : array();
		$new_settings      = array_replace( $current_settings, array_intersect_key( $new_settings, $editable_settings ) );

		if ( $current_settings !== $new_settings && ! Pinterest_For_Woocommerce()::save_settings( $new_settings ) ) {
			return new WP_Error( \PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_options_error', esc_html__( 'There was an error saving the settings.', 'pinterest-for-woocommerce' ), array( 'status' => 500 ) );
		}

		return array(
			PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME => true,
		);
	}
}
