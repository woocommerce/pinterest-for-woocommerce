<?php

namespace Automattic\WooCommerce\Pinterest\Tracking\Data;

use Automattic\WooCommerce\Pinterest\Tracking\Data;

class Search implements Data {

	private $search_query;

	public function __construct( string $search_query ) {
		$this->search_query = $search_query;
	}

	/**
	 * @return string
	 */
	public function get_search_query() {
		return $this->search_query;
	}
}
