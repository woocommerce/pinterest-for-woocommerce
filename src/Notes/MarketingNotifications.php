<?php
/**
 * Marketing notifications for merchants (admin).
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @since   x.x.x
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Notes;

use Automattic\WooCommerce\Pinterest\Notes\Collection\AbstractNote;
use Automattic\WooCommerce\Pinterest\Notes\Collection\EnableCatalogSync;
use Automattic\WooCommerce\Pinterest\Notes\Collection\CatalogSyncErrors;
use Automattic\WooCommerce\Pinterest\Notes\Collection\CompleteOnboardingAfterThreeDays;
use Automattic\WooCommerce\Pinterest\Notes\Collection\CompleteOnboardingReminderAfterSevenDays;
use Automattic\WooCommerce\Pinterest\Notes\Collection\CompleteOnboardingReminderAfterFourteenDays;
use Automattic\WooCommerce\Pinterest\Notes\Collection\CompleteOnboardingReminderAfterThirtyDays;
use Automattic\WooCommerce\Pinterest\Utilities\Utilities;


defined( 'ABSPATH' ) || exit;

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
		EnableCatalogSync::class,
		CatalogSyncErrors::class,
		CompleteOnboardingAfterThreeDays::class,
		CompleteOnboardingReminderAfterSevenDays::class,
		CompleteOnboardingReminderAfterFourteenDays::class,
		CompleteOnboardingReminderAfterThirtyDays::class,
	);

	/**
	 * Trigger inbox messages.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public function init_notifications(): void {
		/*
		 * Check if we have connection timestamp set. If not and the connection
		 * has been made we set the timestamp as now bc we can't know when the
		 * actual connection has been made.
		 */
		if ( 0 === Utilities::get_account_connection_timestamp() && Pinterest_For_Woocommerce()::is_setup_complete() ) {
			Utilities::set_account_connection_timestamp();
		}

		foreach ( self::NOTES as $note ) {
			/** @var AbstractNote $notification */
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
