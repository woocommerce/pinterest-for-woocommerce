<?php
/**
 * Pinterest for WooCommerce Abstract Marketing Note class.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version x.x.x
 */

namespace Automattic\WooCommerce\Pinterest\Notes\Collection;

use Automattic\WooCommerce\Admin\Notes\Note;

defined( 'ABSPATH' ) || exit;

/**
 * Class AbstractCompleteOnboarding.
 *
 * Base class for a set of onboarding reminders.
 *
 * @since x.x.x
 */
abstract class AbstractCompleteOnboarding extends AbstractNote {

	/**
	 * Should the note be added to the inbox.
	 *
	 * @since x.x.x
	 *
	 * @param int $init_timestamp The marketing notifications init timestamp.
	 *
	 * @return boolean
	 */
	public static function should_be_added( int $init_timestamp = 0 ): bool {
		if ( Pinterest_For_Woocommerce()::is_setup_complete() ) {
			return false;
		}

		if ( self::note_exists() ) {
			return false;
		}

		// Are we there yet?
		if ( time() < ( DAY_IN_SECONDS * self::get_days_delay() + $init_timestamp ) ) {
			return false;
		}

		// Check if we have enough orders to proceed.
		$args = array(
			'limit'  => 5,
			'status' => array( 'wc-completed' ),
			'return' => 'ids',
		);

		$orders_ids = wc_get_orders( $args );
		if ( 5 > count( $orders_ids ) ) {
			return false;
		}

		// All preconditions are met, we can send the note.
		return true;
	}

	/**
	 * Add button to Pinterest For WooCommerce landing page.
	 *
	 * @since x.x.x
	 * @param Note $note Note to which we add an action.
	 */
	protected function add_action( $note ): void {
		$note->add_action(
			'goto-pinterest-landing',
			__( 'Complete setup', 'pinterest-for-woocommerce' ),
			wc_admin_url( '&path=/pinterest/landing' )
		);
	}

	/**
	 * Get the number of days that a notification should be delayed.
	 *
	 * This method should be overridden by child classes if needed.
	 *
	 * @return int
	 */
	protected static function get_days_delay(): int {
		return 0;
	}
}
