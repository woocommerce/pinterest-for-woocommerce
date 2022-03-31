<?php

namespace Automattic\WooCommerce\Pinterest\Notes\Collection;

use Automattic\WooCommerce\Pinterest\Notes\MarketingNotifications;

abstract class AbstractCompleteOnboarding extends AbstractNote {

	public function should_be_added(): bool {
		if ( Pinterest_For_Woocommerce()::is_setup_complete() ) {
			return false;
		}

		if ( self::note_exists()) {
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
		// if ( 5 > count( $orders_ids ) ) {
		// 	return false;
		// }

		// All preconditions are met, we can send the note.
		return true;
	}

	/**
	 * Add button to Pinterest For WooCommerce landing page
	 */
	protected function add_action( $note ) {
		$note->add_action(
			'coupon-views',
			__( 'Complete setup', 'pinterest-for-woocommerce' ),
			wc_admin_url( '&path=/pinterest/catalog' )
		);
	}

}
