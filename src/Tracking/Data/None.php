<?php

namespace Automattic\WooCommerce\Pinterest\Tracking\Data;

use Automattic\WooCommerce\Pinterest\Tracking\Data;

/**
 * Used as a stub when no custom data is needed.
 *
 * @since x.x.x
 */
class None extends Data {

	public function __construct( $event_id ) {
		parent::__construct( $event_id );
	}
}
