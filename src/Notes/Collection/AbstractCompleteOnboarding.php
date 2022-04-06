<?php
/**
 * Pinterest for WooCommerce Abstract Marketing Note class.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version x.x.x
 */

namespace Automattic\WooCommerce\Pinterest\Notes\Collection;

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Pinterest\Notes\MarketingNotifications;

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
	 * @return boolean
	 */
	public function should_be_added(): bool {
		if ( Pinterest_For_Woocommerce()::is_setup_complete() ) {
			return false;
		}

		if ( self::note_exists() ) {
			return false;
		}

		// Are we there yet?
		if ( time() < ( DAY_IN_SECONDS * static::DELAY + MarketingNotifications::get_init_timestamp() ) ) {
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

}
