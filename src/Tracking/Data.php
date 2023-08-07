<?php
/**
 * Pinterest tracking base data class.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version 1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\Tracking;

/**
 * Common data class to store event related data.
 */
class Data {

	/**
	 * A unique event id.
	 *
	 * @var string
	 */
	private $event_id;

	/**
	 * @param string $event_id - A unique event id.
	 */
	public function __construct( $event_id ) {
		$this->event_id = $event_id;
	}

	/**
	 * Get unique event id.
	 *
	 * @return string
	 */
	public function get_event_id() {
		return $this->event_id;
	}
}
