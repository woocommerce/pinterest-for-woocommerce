<?php

namespace Automattic\WooCommerce\Pinterest\Tracking;

class Data {

	private $event_id;

	public function __construct( $event_id ) {
		$this->event_id = $event_id;
	}

	public function get_event_id() {
		return $this->event_id;
	}
}
