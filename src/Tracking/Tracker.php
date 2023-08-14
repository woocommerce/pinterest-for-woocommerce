<?php
/**
 * Pinterest tracker interface.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version 1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\Tracking;

use Throwable;

/**
 * Interface for Pinterest tracker implementations.
 */
interface Tracker {

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
