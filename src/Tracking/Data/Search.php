<?php

namespace Automattic\WooCommerce\Pinterest\Tracking\Data;

use Automattic\WooCommerce\Pinterest\Tracking\Data;

class Search extends Data {

	private $search_query;

	public function __construct( $event_id, $search_query ) {
		parent::__construct( $event_id );
		$this->search_query = $search_query;
	}

	/**
	 * @return string
	 */
	public function get_search_query() {
		return $this->search_query;
	}
}
