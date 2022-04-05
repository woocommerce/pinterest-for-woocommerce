<?php
/**
 * Marketing notifications for merchants (admin).
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @since   x.x.x
 */

namespace Automattic\WooCommerce\Pinterest\Notes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class responsible for displaying inbox notifications for the merchant.
 *
 * In the specification for the notes we have that the notification should
 * be sent some time after the plugin installation. There is no retroactive
 * way of figuring out when the plugin was first installed. So we count
 *
 * @since x.x.x
 */
class MarketingNotifications {

	// All options common prefix.
	const OPTIONS_PREFIX = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-marketing-notifications';

	// Timestamp option marking the moment we start to count time.
	const INIT_TIMESTAMP = self::OPTIONS_PREFIX . '-init-timestamp';

	// List of marketing notifications that we want to send.
	const NOTES = array(
		'EnableCatalogSync',
		'CatalogSyncErrors',
		'CompleteOnboardingAfterThreeDays',
		'CompleteOnboardingReminderAfterSevenDays',
		'CompleteOnboardingReminderAfterFourteenDays',
		'CompleteOnboardingReminderAfterThirtyDays',
	);

	/**
	 * Trigger inbox messages.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public function init_notifications(): void {

		foreach ( self::NOTES as $note ) {
			$note         = 'Automattic\WooCommerce\Pinterest\Notes\Collection\\' . $note;
			$notification = new $note();
			if ( ! $notification->should_be_added() ) {
				continue;
			}
			$notification
				->prepare_note()
				->save();
		}
	}

	/**
	 * Check if the init timestamp exist.
	 * Initialize if not.
	 *
	 * @since x.x.x
	 * @return int Initialization timestamp.
	 */
	public static function get_init_timestamp(): int {
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
