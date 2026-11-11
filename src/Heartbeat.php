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
 * @class   Heartbeat
 * @since   1.1.0
 * @version 1.6.3
 */
class Heartbeat {

	/**
	 * Hook name for daily heartbeat.
	 */
	const DAILY  = 'pinterest_for_woocommerce_daily_heartbeat';
	const HOURLY = 'pinterest_for_woocommerce_hourly_heartbeat';

	const WEEKLY = 'pinterest_for_woocommerce_weekly_heartbeat';

	/**
	 * Schedule heartbeat events.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function schedule_events() {
		if ( ! Pinterest_For_Woocommerce::is_connected() ) {
			return;
		}

		$intervals = array(
			self::DAILY  => DAY_IN_SECONDS,
			self::HOURLY => HOUR_IN_SECONDS,
			self::WEEKLY => WEEK_IN_SECONDS,
		);

		foreach ( $intervals as $hook => $interval ) {
			if ( as_has_scheduled_action( $hook, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX ) ) {
				continue;
			}

			$failed_actions = as_get_scheduled_actions(
				array(
					'hook'             => $hook,
					'group'            => PINTEREST_FOR_WOOCOMMERCE_PREFIX,
					'status'           => \ActionScheduler_Store::STATUS_FAILED,
					'modified'         => as_get_datetime_object( time() - $interval ),
					'modified_compare' => '>=',
					'per_page'         => 1,
					'orderby'          => 'modified',
					'order'            => 'DESC',
				),
				'ids'
			);

			// Retry a recent failure one interval after its last attempt, not after this request.
			$start = time();
			if ( $failed_actions ) {
				$failed_at = \ActionScheduler_Store::instance()->get_date( reset( $failed_actions ) )->getTimestamp();
				$start     = max( $start, $failed_at + $interval );
			}
			as_schedule_recurring_action( $start, $interval, $hook, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX, true );
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
		as_unschedule_all_actions( self::WEEKLY, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
	}
}
