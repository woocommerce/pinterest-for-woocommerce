<?php

namespace Automattic\WooCommerce\Pinterest\Tracking;

interface Tracker {

	/**
	 * Tracks the event.
	 *
	 * @since x.x.x
	 *
	 * @param string $event_name
	 * @param Data   $data
	 *
	 * @return true
	 */
	public function track_event( string $event_name, Data $data );
}
