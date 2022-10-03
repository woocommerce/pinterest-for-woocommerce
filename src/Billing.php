<?php
/**
 * Billing endpoint helper methods.
 *
 * @package Pinterest_For_WooCommerce/Classes
 */

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Pinterest\API\Base;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class with functions for billing endpoint.
 */
class Billing {

	/**
	 * Initialize Billing actions and Action Scheduler hooks.
	 *
	 * @since x.x.x
	 */
	public static function schedule_event() {
		add_action( Heartbeat::DAILY, array( __CLASS__, 'handle_billing_setup_check' ) );
	}

	/**
	 * Check if the advertiser has set the billing data.
	 *
	 * @since x.x.x
	 *
	 * @return mixed
	 */
	public static function handle_billing_setup_check() {

		Pinterest_For_Woocommerce()::add_billing_setup_info_to_account_data();

		return true;
	}

	/**
	 * Helper function to check if billing has been set up.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	public static function has_billing_set_up(): bool {

		if ( ! Pinterest_For_Woocommerce()::get_data( 'is_advertiser_connected' ) ) {
			// Advertiser not connected, we can't establish if billing is set up.
			return false;
		}

		$advertiser_id = Pinterest_For_Woocommerce()::get_setting( 'tracking_advertiser' );

		if ( false === $advertiser_id ) {
			// No advertiser id stored. But we are connected. This is an abnormal state that should not happen.
			Logger::log( __( 'Advertiser connected but the connection id is missing.', 'pinterest-for-woocommerce' ) );
			return false;
		}

		try {
			$result = Base::get_advertiser_billing_profile( $advertiser_id );
			if ( 'success' !== $result['status'] ) {
				return false;
			}

			$billing_profile_data = (array) $result['data'];

			return (bool) $billing_profile_data['is_billing_setup'];

		} catch ( Throwable $th ) {

			Logger::log( $th->getMessage(), 'error' );
			return false;

		}
	}
}
