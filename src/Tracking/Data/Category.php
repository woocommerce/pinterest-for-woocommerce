<?php
/**
 * Pinterest tracking category data class.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version 1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\Tracking\Data;

use Automattic\WooCommerce\Pinterest\Tracking\Data;

/**
 * Category data class to hold category name and id data.
 */
class Category extends Data {

	/**
	 * WooCommerce category id.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Category name.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * @param string $event_id  A unique event id.
	 * @param string $id        Product category id.
	 * @param string $name      Product category name.
	 */
	public function __construct( $event_id, $id, $name ) {
		parent::__construct( $event_id );
		$this->id    = $id;
		$this->name  = $name;
	}

	/**
	 * @return mixed
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * @return mixed
	 */
	public function get_name() {
		return $this->name;
	}
}
