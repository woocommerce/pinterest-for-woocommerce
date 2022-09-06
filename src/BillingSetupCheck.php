<?php
/**
 * Pinterest for WooCommerce Ads Billing Setup Check.
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.10
 */

namespace Automattic\WooCommerce\Pinterest;

use Exception;
use Throwable;
use Automattic\WooCommerce\Pinterest\API\Base;
use Automattic\WooCommerce\Pinterest\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Handling billing setup check.
 */
class BillingSetupCheck {

	const ACTION_HANDLE_BILLING_SETUP_CHECK = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-check-billing-setup';

	/**
	 * Initialize BillingSetupCheck actions and Action Scheduler hooks.
	 *
	 * @since x.x.x
	 */
	public static function maybe_init() {
		add_action( self::ACTION_HANDLE_BILLING_SETUP_CHECK, array( __CLASS__, 'handle_billing_setup_check' ) );
		if ( false === as_has_scheduled_action( self::ACTION_HANDLE_BILLING_SETUP_CHECK, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX ) ) {
			as_schedule_recurring_action( time() + 10, 10 * MINUTE_IN_SECONDS, self::ACTION_HANDLE_BILLING_SETUP_CHECK, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		}
	}

	/**
	 * Check if the advertiser has set the billing data.
	 *
	 * @since x.x.x
	 *
	 * @return mixed
	 *
	 * @throws Exception PHP Exception.
	 */
	public function handle_billing_setup_check() {

		$advertiser_id = Pinterest_For_Woocommerce()::get_setting( 'tracking_advertiser' );

		if ( ! $advertiser_id ) {
			return true;
		}

		try {
			$billing_data = Base::get_advertiser_billing_data( $advertiser_id );

			// TODO: check billing data when API info is available.
			// We can save the billing data in the plugins's option.
			// Pinterest_For_Woocommerce()::save_setting( 'billing_data', $billing_data );

			return true;

		} catch ( Throwable $th ) {

			Logger::log( sprintf( esc_html__( 'There was an error getting the billing data: [%s]', 'pinterest-for-woocommerce' ), $th->getMessage() ), 'error' );

			return false;
		}

	}

	/**
	 * Stop feed generator jobs.
	 *
	 * @since x.x.x
	 */
	public static function cancel_jobs() {
		as_unschedule_all_actions( self::ACTION_HANDLE_BILLING_SETUP_CHECK, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
	}
}
