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

	private $id;

	private $name;

	/**
	 * @param string $event_id - A unique event id.
	 * @param string $id       - Product category id.
	 * @param string $name     - Product category name.
	 */
	public function __construct( $event_id, $id, $name ) {
		parent::__construct( $event_id );
		$this->id    = $id;
		$this->name  = $name;
	}

	/**
	 * @return mixed
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return mixed
	 */
	public function getName() {
		return $this->name;
	}
}
