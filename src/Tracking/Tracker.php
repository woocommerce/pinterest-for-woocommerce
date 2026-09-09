<?php
/**
 * Pinterest tracker interface.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version 1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\Tracking;

use Automattic\WooCommerce\Pinterest\Tracking;
use Throwable;

/**
 * Interface for Pinterest tracker implementations.
 */
abstract class Tracker {

	/**
	 * Maps tracking events to corresponding tracker methods and conversions API events names.
	 *
	 * @since 1.4.0
	 */
	const EVENT_MAP = array(
		Tracking::EVENT_PAGE_VISIT    => 'page_visit',
		Tracking::EVENT_SEARCH        => 'search',
		Tracking::EVENT_VIEW_CATEGORY => 'view_category',
		Tracking::EVENT_ADD_TO_CART   => 'add_to_cart',
		Tracking::EVENT_CHECKOUT      => 'checkout',
	);

	/**
	 * Initialises hooks a tracker need to operate.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public function init_hooks() {
	}

	/**
	 * Disables hooks a tracker could set.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public function disable_hooks() {
	}

	/**
	 * Returns a hashed external identifier for the logged-in customer.
	 *
	 * @return string|false Hashed customer ID, or false for guests.
	 */
	protected static function maybe_get_hashed_customer_external_id() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		return hash( 'sha256', (string) get_current_user_id() );
	}

	/**
	 * Tracks the event.
	 *
	 * @since 1.4.0
	 *
	 * @param string $event_name - A unique event id.
	 * @param Data   $data       - Data class which holds corresponding even data.
	 *
	 * @throws Throwable In case of an API error.
	 *
	 * @return true
	 */
	abstract public function track_event( string $event_name, Data $data );
}
