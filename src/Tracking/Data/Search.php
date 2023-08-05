<?php

namespace Automattic\WooCommerce\Pinterest\Tracking\Data;

use Automattic\WooCommerce\Pinterest\Tracking\Data;

/**
 * Custom data for events which require search string e.g. search, view search results, etc.
 *
 * @link https://developers.pinterest.com/docs/conversions/best/#Required,%20recommended,%20and%20optional%20fields
 *
 * @since x.x.x
 */
class Search extends Data {

	private $search_query;

	public function __construct( $event_id, $search_query ) {
		parent::__construct( $event_id );
		$this->search_query = $search_query;
	}

	/**
	 * Returns search query string.
	 *
	 * @since x.x.x
	 *
	 * @return string
	 */
	public function get_search_query() {
		return $this->search_query;
	}
}
