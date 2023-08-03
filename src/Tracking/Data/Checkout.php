<?php

namespace Automattic\WooCommerce\Pinterest\Tracking\Data;

use Automattic\WooCommerce\Pinterest\Tracking\Data;

class Checkout implements Data {

	private $order_id;

	private $price;

	private $quantity;

	private $currency;

	private $items;

	public function __construct( $order_id, $price, $quantity, $currency, $items ) {
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
	 * @return mixed
	 */
	public function get_items() {
		return $this->items;
	}
}
