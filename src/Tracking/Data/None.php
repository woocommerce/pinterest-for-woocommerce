<?php

namespace Automattic\WooCommerce\Pinterest\Tracking\Data;

use Automattic\WooCommerce\Pinterest\Tracking\Data;

class None extends Data {

	public function __construct( $event_id ) {
		parent::__construct( $event_id );
	}
}
