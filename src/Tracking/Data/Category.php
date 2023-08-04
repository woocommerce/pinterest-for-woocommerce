<?php

namespace Automattic\WooCommerce\Pinterest\Tracking\Data;

use Automattic\WooCommerce\Pinterest\Tracking\Data;

class Category extends Data {

	private $product_category;

	private $category_name;

	public function __construct( $event_id, $product_category, $category_name ) {
		parent::__construct( $event_id );
		$this->product_category = $product_category;
		$this->category_name  = $category_name;
	}

	/**
	 * @return mixed
	 */
	public function getProductCategory() {
		return $this->product_category;
	}

	/**
	 * @return mixed
	 */
	public function getCategoryName() {
		return $this->category_name;
	}
}
