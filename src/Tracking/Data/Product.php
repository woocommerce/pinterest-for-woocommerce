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
 * Product data class. Hold product related data for event.
 *
 * @since x.x.x
 */
class Product extends Data {

	/**
	 * Product WooCommerce id.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Product name.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Product category.
	 *
	 * @var string
	 */
	private $category;

	/**
	 * Product brand.
	 *
	 * @var string
	 */
	private $brand;

	/**
	 * Product price.
	 *
	 * @var string
	 */
	private $price;

	/**
	 * Currency code.
	 *
	 * @var string
	 */
	private $currency;

	/**
	 * Number of products.
	 *
	 * @var string
	 */
	private $quantity;

	/**
	 * @param string $event_id - A unique event ID.
	 * @param string $id       - Product ID.
	 * @param string $name     - Product name.
	 * @param string $category - Product categories.
	 * @param string $brand    - Product brand.
	 * @param string $price    - Product price.
	 * @param string $currency - Product currency.
	 * @param string $quantity - Product quantity.
	 */
	public function __construct( $event_id, $id, $name, $category, $brand, $price, $currency, $quantity ) {
		parent::__construct( $event_id );
		$this->id       = $id;
		$this->name     = $name;
		$this->category = $category;
		$this->brand    = $brand;
		$this->price    = $price;
		$this->currency = $currency;
		$this->quantity = $quantity;
	}

	/**
	 * @return mixed Get Product ID.
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * @return mixed Get Product name.
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * @return mixed Get Product category.
	 */
	public function get_category() {
		return $this->category;
	}

	/**
	 * @return mixed Product brand.
	 */
	public function get_brand() {
		return $this->brand;
	}

	/**
	 * @return mixed Get Product price.
	 */
	public function get_price() {
		return $this->price;
	}

	/**
	 * @return mixed Get Product currency code.
	 */
	public function get_currency() {
		return $this->currency;
	}

	/**
	 * @return mixed Get Product quantity.
	 */
	public function get_quantity() {
		return $this->quantity;
	}
}
