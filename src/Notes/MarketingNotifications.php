<?php
/**
 * Marketing notifications for merchants (admin).
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @since   x.x.x
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class responsible for displaying inbox notifications for the merchant.
 *
 * In the specification for the notes we have that the notification should
 * be sent some time after the plugin installation. There is no retroacive
 * way of figuring out when the plugin was first installed. So we count
 * @since x.x.x
 */
class MarketingNotifications {

	// All options common prefix.
	const OPTIONS_PREFIX = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-marketing-notifications';

	// Timestamp option marking the moment we start to count time.
	const INIT_TIMESTAMP = self::OPTIONS_PREFIX . '-init-timestamp';

	// Indicates if the complete onboarding note has been sent.
	const COMPLETE_ONBOARDING = self::OPTIONS_PREFIX . '-complete-onboarding';

	// True if all marketing notices have been sent.
	const COMPLETED = self::OPTIONS_PREFIX . 'completed';

	public function init_notifications() {
		// Check if we are not done.
		if ( (bool) get_option( self::COMPLETED ) ){
			return;
		}


	}

	/**
	 * Maybe send complete_onboarding_note
	 * Sent 3 days after the init timestamp when the user hasn’t completed
	 * Pinterest setup & store has more than 5+ sales.
	 *
	 * @since x.x.x
	 * @return bool;
	 */
	private function maybe_schedule_complete_onboarding_note() {
		// Check if we are not done.
		if ( (bool) get_option( self::COMPLETE_ONBOARDING ) ){
			return;
		}


		// Are we there yet?
		if ( time() < ( DAY_IN_SECONDS * 3 + $this->get_init_timestamp() ) ) {
			return
		}
	}

	/**
	 * Check if the init timestamp exist.
	 * Initialize if not.
	 *
	 * @since x.x.x
	 * @return int Initialization timestamp.
	 */
	private function get_init_timestamp(): int {
		$timestamp = get_option( self::INIT_TIMESTAMP );
		if ( false !== $timestamp ) {
			// Timestamp already init, return.
			return (int) $timestamp;
		}

		// Timestamp not initialized, create a new one.
		$timestamp = time();
		add_option( self::INIT_TIMESTAMP, $timestamp );
		return $timestamp;
	}



}
