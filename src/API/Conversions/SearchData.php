<?php

namespace Automattic\WooCommerce\Pinterest\API\Conversions;

/**
 * @link https://developers.pinterest.com/docs/conversions/best/#Required,%20recommended,%20and%20optional%20fields
 *
 * @since x.x.x
 */
class SearchData implements CustomData {

	/**
	 * @var string A search string related to the conversion event.
	 */
	private string $search_string;

	public function __construct(string $search_string ) {
		$this->search_string = $search_string;
	}

	/**
	 * @return string
	 */
	public function get_search_string(): string
	{
		return $this->search_string;
	}
}
