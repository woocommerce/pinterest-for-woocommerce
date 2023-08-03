<?php

namespace Automattic\WooCommerce\Pinterest\Tracking\Data;

use Automattic\WooCommerce\Pinterest\Tracking\Data;

class Product implements Data {

	private $id;

	private $name;

	private $category;

	private $brand;

	private $price;

	private $currency;

	private $quantity;

	public function __construct( $id, $name, $category, $brand, $price, $currency, $quantity ) {
		$this->id       = $id;
		$this->name     = $name;
		$this->category = $category;
		$this->brand    = $brand;
		$this->price    = $price;
		$this->currency = $currency;
		$this->quantity = $quantity;
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

	public function get_category() {
		return $this->category;
	}

	public function get_brand() {
		return $this->brand;
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
	public function get_currency() {
		return $this->currency;
	}

	public function get_quantity() {
		return $this->quantity;
	}
}
