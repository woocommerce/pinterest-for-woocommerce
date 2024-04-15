<?php
/**
 * Billing endpoint helper methods.
 *
 * @package Pinterest_For_WooCommerce/Classes
 */

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Pinterest\API\APIV5;
use Automattic\WooCommerce\Pinterest\API\Base;
use Pinterest_For_Woocommerce;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class with functions for billing endpoint.
 */
class Billing {

	const CHECK_BILLING_SETUP_OFTEN         = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-check-billing-transient';
	const CHECK_BILLING_SETUP_ONCE_PER_HOUR = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-check-billing-once-per-hour-transient';
	/**
	 * Initialize Billing actions and Action Scheduler hooks.
	 *
	 * @since 1.2.5
	 */
	public static function schedule_event() {
		add_action( Heartbeat::DAILY, array( __CLASS__, 'handle_billing_setup_check' ) );
	}

	/**
	 * Check if the advertiser has set the billing data.
	 *
	 * @since 1.2.5
	 *
	 * @return mixed
	 */
	public static function handle_billing_setup_check() {

		self::update_billing_information();

		return true;
	}

	/**
	 * Check if we are during the period of frequent billing checks.
	 * If the billing has been verified as correct we don't want the frequent check.
	 *
	 * @since 1.2.5
	 *
	 * @return bool
	 */
	public static function should_check_billing_setup_often() {
		/*
		 * Check if we have verified a correct billing setup.
		 */
		$account_data       = Pinterest_For_Woocommerce()::get_setting( 'account_data' );

		$has_billing_setup  = is_array( $account_data ) && ( $account_data['is_billing_setup'] ?? false );
		$should_check_often = false !== get_transient( self::CHECK_BILLING_SETUP_OFTEN );
		if ( $has_billing_setup && $should_check_often ) {
			/*
			 * We are just after initial setup or billing button click and billing setup is correct.
			 * Assume that the user has just crated the setup and we have caught it. We don't need to check for now.
			 */
			return false;
		}

		if ( $should_check_often ) {
			return true;
		}

		if ( false !== get_transient( self::CHECK_BILLING_SETUP_ONCE_PER_HOUR ) ) {
			// Last check was less then hour ago. Skip this one.
			return false;
		}

		return true;
	}

	/**
	 * Mark billing setup check as required often.
	 *
	 * @since 1.2.5
	 *
	 * @param int $duration For how lon frequent billing check should happen.
	 *
	 * @return void
	 */
	public static function check_billing_setup_often( $duration = HOUR_IN_SECONDS ) {
		set_transient( self::CHECK_BILLING_SETUP_OFTEN, true, $duration );
	}

	/**
	 * Clear billing check transient.
	 *
	 * @since 1.2.5
	 *
	 * @return void
	 */
	public static function do_not_check_billing_setup_often() {
		delete_transient( self::CHECK_BILLING_SETUP_OFTEN );
	}

	/**
	 * Mark setup as checked. This will delay next setup for an hour.
	 *
	 * @since 1.2.5
	 *
	 * @return void
	 */
	public static function mark_billing_setup_checked() {
		set_transient( self::CHECK_BILLING_SETUP_ONCE_PER_HOUR, true, HOUR_IN_SECONDS );
	}

	/**
	 * Helper function to check if billing has been set up.
	 *
	 * @since 1.2.5
	 * @return bool
	 */
	public static function has_billing_set_up(): bool {
		if ( ! Pinterest_For_Woocommerce::is_connected() ) {
			// Advertiser not connected, we can't establish if billing is set up.
			return false;
		}

		try {
			$ad_account_id   = Pinterest_For_Woocommerce()::get_setting( 'tracking_advertiser' );
			$active_profiles = APIV5::get_active_billing_profiles( $ad_account_id );

			return array_reduce(
				$active_profiles['items'] ?? array(),
				function ( $carry, $item ) {
					if ( $carry ) {
						return $carry;
					}
					return 'VALID' === $item['status'];
				},
				false
			);
		} catch ( Throwable $th ) {
			Logger::log( $th->getMessage(), 'error' );
			return false;
		}
	}

	/**
	 * Fetch billing setup information from API and update billing status in options.
	 * Using this function makes sense only when we have a connected advertiser.
	 *
	 * @since 1.2.5
	 * @since x.x.x Split storing billing setup status and updating billing setup status.
	 * @since x.x.x Moved from class-pinterest-for-woocommerce.php
	 *
	 * @return bool Wether billing is set up or not.
	 */
	public static function update_billing_information() {
		$status = self::has_billing_set_up();
		self::add_billing_setup_status_to_account_data( $status );
		return $status;
	}

	/**
	 * Add billing setup status to the account data option.
	 *
	 * @since x.x.x
	 *
	 * @param bool $status The billing setup status.
	 *
	 * @return void
	 */
	public static function add_billing_setup_status_to_account_data( $status ) {
		$account_data                     = Pinterest_For_Woocommerce()::get_setting( 'account_data' );
		$account_data['is_billing_setup'] = $status;
		Pinterest_For_Woocommerce()::save_setting( 'account_data', $account_data );
	}
}
