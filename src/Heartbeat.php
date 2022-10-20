<?php
/**
 * Pinterest for WooCommerce Heartbeat class.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version 1.1.0
 */

namespace Automattic\WooCommerce\Pinterest;

use WC_Queue_Interface;

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
	 * WooCommerce Queue Interface.
	 *
	 * @var WC_Queue_Interface
	 */
	protected $queue;

	/**
	 * Heartbeat constructor.
	 *
	 * @since 1.1.0
	 * @param WC_Queue_Interface $queue WC Action Scheduler proxy.
	 */
	public function __construct( WC_Queue_Interface $queue ) {
		$this->queue = $queue;
	}

	/**
	 * Add hooks.
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'schedule_events' ) );
	}

	/**
	 * Schedule heartbeat events.
	 *
	 * @since 1.1.0
	 */
	public function schedule_events() {
		if ( null === $this->queue->get_next( self::DAILY, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX ) ) {
			$this->queue->schedule_recurring( time(), DAY_IN_SECONDS, self::DAILY, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		}

		if ( null === $this->queue->get_next( self::HOURLY, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX ) ) {
			$this->queue->schedule_recurring( time(), HOUR_IN_SECONDS, self::HOURLY, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		}
	}

}
