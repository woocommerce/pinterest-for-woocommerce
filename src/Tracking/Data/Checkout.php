<?php
/**
 * Pinterest for WooCommerce Tracking Data classes.
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\Tracking\Data;

use Automattic\WooCommerce\Pinterest\Tracking\Data;

/**
 * Holds Checkout event related data to pass it into trackers.
 */
class Checkout extends Data {

	/**
	 * @var string WooCommerce order id.
	 */
	private $order_id;

	/**
	 * @var string WooCommerce order amount.
	 */
	private $price;

	/**
	 * @var int Number of items in the order.
	 */
	private $quantity;

	/**
	 * @var string order currency code.
	 */
	private $currency;

	/**
	 * @var Product[] an array of products involved into checkout.
	 */
	private $items;

	/**
	 * @param string    $event_id - A unique event id as a requirement by Pinterest for deduplication purposes.
	 * @param           $order_id - WooCommerce Order ID.
	 * @param           $price    - WooCommerce total order amount.
	 * @param           $quantity - Number of items.
	 * @param string    $currency - Order currency.
	 * @param Product[] $items    - An array of ordered items.
	 */
	public function __construct( $event_id, $order_id, $price, $quantity, $currency, $items ) {
		parent::__construct( $event_id );
		$this->order_id = $order_id;
		$this->price    = $price;
		$this->quantity = $quantity;
		$this->currency = $currency;
		$this->items    = $items;
	}

	/**
	 * @return mixed
	 */
	public function get_order_id() {
		return $this->order_id;
	}

	/**
	 * @return mixed
	 */
	public function get_price() {
		return $this->price;
	}

	/**
	 * @return mixed
	 */
	public function get_quantity() {
		return $this->quantity;
	}

	/**
	 * @return mixed
	 */
	public function get_currency() {
		return $this->currency;
	}

	/**
	 * @return Product[]
	 */
	public function get_items() {
		return $this->items;
	}
}
