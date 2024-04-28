<?php
/**
 * Pinterest for WooCommerce Heartbeat class.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version 1.1.0
 */

namespace Automattic\WooCommerce\Pinterest;

use Pinterest_For_Woocommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Class Heartbeat.
 *
 * Responsible for scheduling cron heartbeat hooks.
 * Useful for performing various async actions of low intensity.
 *
 * @since 1.1.0
 */
class Heartbeat {

	/**
	 * Hook name for daily heartbeat.
	 */
	const DAILY  = 'pinterest_for_woocommerce_daily_heartbeat';
	const HOURLY = 'pinterest_for_woocommerce_hourly_heartbeat';

	/**
	 * Schedule heartbeat events.
	 *
	 * @since 1.1.0
	 */
	public static function schedule_events() {
		if ( ! Pinterest_For_Woocommerce::is_connected() ) {
			return;
		}

		if ( ! as_has_scheduled_action( self::DAILY, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX ) ) {
			as_schedule_recurring_action( time(), DAY_IN_SECONDS, self::DAILY, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		}

		if ( ! as_has_scheduled_action( self::HOURLY, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX ) ) {
			as_schedule_recurring_action( time(), HOUR_IN_SECONDS, self::HOURLY, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		}
	}

	/**
	 * Cancels all the scheduled jobs.
	 *
	 * @sinxe 1.4.0
	 *
	 * @return void
	 */
	public static function cancel_jobs() {
		as_unschedule_all_actions( self::DAILY, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		as_unschedule_all_actions( self::HOURLY, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
	}
}
