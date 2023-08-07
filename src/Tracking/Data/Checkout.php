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
	 * WooCommerce order id.
	 *
	 * @var string
	 */
	private $order_id;

	/**
	 * WooCommerce order amount.
	 *
	 * @var string
	 */
	private $price;

	/**
	 * Number of items in the order.
	 *
	 * @var int
	 */
	private $quantity;

	/**
	 * Order currency code.
	 *
	 * @var string
	 */
	private $currency;

	/**
	 * An array of products involved into checkout.
	 *
	 * @var Product[]
	 */
	private $items;

	/**
	 * @param string    $event_id - A unique event id as a requirement by Pinterest for deduplication purposes.
	 * @param string    $order_id - WooCommerce Order ID.
	 * @param string    $price    - WooCommerce total order amount.
	 * @param string    $quantity - Number of items.
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
