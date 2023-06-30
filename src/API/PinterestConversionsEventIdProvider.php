<?php
/**
 * @since x.x.x
 */

/**
 * @since x.x.x
 */
class PinterestConversionsEventIdProvider {

	/**
	 * An array of event names to event IDs map for the Pinterest Conversions API and Pinterest Tag.
	 *
	 * @var string[]
	 */
	private static array $event_ids = [];

	/**
	 * Pinterest Tag and Pinterest API for Conversions have different event names.
	 * Since Pinterest Tag was here first we use its event names as the keys.
	 *
	 * @var array|string[] An array of event names to Pinterest Tag API names map.
	 */
	private static array $tag_api_name_map = [
		 'pagevisit'         => 'page_view',
		 'addtocart'         => 'add_to_cart',
		 'checkout'          => 'checkout',
		 'lead'              => 'lead',
		 'purchase'          => 'purchase',
		 'search'            => 'search',
		 'viewcategory'      => 'view_category',
		 'viewitem'          => 'view_item',
		 'viewsearchresults' => 'view_search_results',
	];

	/**
	 * @since x.x.x
	 *
	 * @param string $event_name
	 * @return string
	 */
	public static function get_event_id( string $event_name ): string {
		$event_name = static::$tag_api_name_map[ strtolower( $event_name ) ] ?? $event_name;
		return static::$event_ids[ $event_name ] ?? static::generate_event_id( $event_name );
	}

	/**
	 * @since x.x.x
	 *
	 * @param string $event_name
	 * @return string
	 */
	private static function generate_event_id( string $event_name ): string {
		$id = uniqid( 'pinterest-for-woocommerce-conversions-event-id-for-' . $event_name );
		static::$event_ids[ $event_name ] = $id;
		return $id;
	}

	/**
	 * @since x.x.x
	 *
	 * @param string $pinterest_tag_event_name
	 * @return mixed|string
	 */
	public static function get_event_name_by_pinterest_tag_event_name( string $pinterest_tag_event_name ) {
		return static::$tag_api_name_map[ strtolower( $pinterest_tag_event_name ) ] ?? $pinterest_tag_event_name;
	}
}
