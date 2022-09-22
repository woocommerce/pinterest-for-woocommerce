<?php
/**
 * Pinterest for WooCommerce Ads Billing Setup Check.
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.10
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Handling billing setup check.
 */
class BillingSetupCheck {

	/**
	 * Initialize BillingSetupCheck actions and Action Scheduler hooks.
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
	public function handle_billing_setup_check() {

		Pinterest_For_Woocommerce()::add_billing_setup_info_to_account_data();

		return true;
	}

}
