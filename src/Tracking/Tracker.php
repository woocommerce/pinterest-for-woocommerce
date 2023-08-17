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
interface Tracker {

	/**
	 * Maps tracking events to corresponding tracker methods and conversions API events names.
	 *
	 * @since x.x.x
	 */
	const EVENT_MAP = array(
		Tracking::EVENT_PAGE_VISIT    => 'page_visit',
		Tracking::EVENT_SEARCH        => 'search',
		Tracking::EVENT_VIEW_CATEGORY => 'view_category',
		Tracking::EVENT_ADD_TO_CART   => 'add_to_cart',
		Tracking::EVENT_CHECKOUT      => 'checkout',
	);

	/**
	 * Tracks the event.
	 *
	 * @since x.x.x
	 *
	 * @param string $event_name - A unique event id.
	 * @param Data   $data       - Data class which holds corresponding even data.
	 *
	 * @throws Throwable In case of an API error.
	 *
	 * @return true
	 */
	public function track_event( string $event_name, Data $data );
}
