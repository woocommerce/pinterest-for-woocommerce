<?php
/**
 * Pinterest Tracking data class.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version 1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\Tracking\Data;

use Automattic\WooCommerce\Pinterest\Tracking\Data;

/**
 * Used as a stub when no custom data is needed.
 *
 * @since x.x.x
 */
class None extends Data {

	/**
	 * @param string $event_id - A unique event id.
	 */
	public function __construct( $event_id ) {
		parent::__construct( $event_id );
	}
}
