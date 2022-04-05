<?php
/**
 * Pinterest for WooCommerce Heartbeat class.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version x.x.x
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
 * @since x.x.x
 */
class Heartbeat {

	/**
	 * Hook name for daily heartbeat.
	 */
	const DAILY = 'pinterest_for_woocommerce_daily_heartbeat';

	/**
	 * Cron name string.
	 *
	 * @var string
	 */
	protected $daily_cron_name = 'pinterest_for_woocommerce_daily_heartbeat_cron';

	/**
	 * WooCommerce Queue Interface.
	 *
	 * @var WC_Queue_Interface
	 */
	protected $queue;

	/**
	 * Heartbeat constructor.
	 *
	 * @since x.x.x
	 * @param WC_Queue_Interface $queue WC Action Scheduler proxy.
	 */
	public function __construct( WC_Queue_Interface $queue ) {
		$this->queue = $queue;
	}

	/**
	 * Add hooks.
	 */
	public function init() {
		add_action( 'init', array( $this, 'schedule_cron_events' ) );
		add_action( $this->daily_cron_name, array( $this, 'schedule_daily_action' ) );
	}

	/**
	 * Schedule heartbeat cron events.
	 *
	 * WP Cron events are stored in an auto-loaded option so the performance impact
	 * is much lower than checking and scheduling an Action Scheduler action.
	 *
	 * @since x.x.x
	 */
	public function schedule_cron_events() {
		if ( ! wp_next_scheduled( $this->daily_cron_name ) ) {
			wp_schedule_event( time(), 'daily', $this->daily_cron_name );
		}
	}

	/**
	 * Schedule the daily heartbeat action to run immediately.
	 *
	 * Scheduling an action frees up WP Cron to process more jobs in the current request.
	 * Action Scheduler has greater throughput so running our checks there is better.
	 *
	 * @since x.x.x
	 */
	public function schedule_daily_action() {
		$this->queue->add( self::DAILY );
	}

}
