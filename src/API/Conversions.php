<?php

use Automattic\WooCommerce\Pinterest\API\APIV5;

class Conversions {
	/**
	 * An array of event names to event IDs map for the Pinterest Conversions API and Pinterest Tag.
	 *
	 * @param string $event_name
	 * @param array $data
	 */
	public static function add_event( string $event_name, array $data = [] ) {
		$data = array_merge(
			$data,
			[
				'ad_account_id' => '',
				'event_name'    => $event_name,
				'action_source' => 'website',
				'event_time'    => time(),
				'user_data'     => [
					'client_ip_address' => '',
					'client_user_agent' => '',
				],
				'event_id'      => PinterestConversionsEventIdProvider::get_event_id( $event_name ),
				'event_source_url' => '',

			]
		);

		try {
			APIV5::make_request(
				'',
				'POST',
				$data
			);
		} catch ( Exception $e ) {
			// Do nothing.
		}
	}

	/**
	 * Custom data related to add to cart and checkout events.
	 *
	 * @since x.x.x
	 * @link https://developers.pinterest.com/docs/conversions/best/#Required#Custom%20data%20object
	 *
	 * @param array $event_data
	 * @return array|string[][]
	 */
	private static function add_cart_and_checkout_custom_data( array $event_data ): array {
		return array_merge(
			$event_data,
			[
				'custom_data' => [
					'currency'    => '',
					'value'       => '',
					'content_ids' => '',
					'contents'    => '',
					'num_items'   => '',
					'order_id'    => '',
				],
			]
		);
	}

	private static function add_search_custom_data( array $event_data ): array {
		return array_merge(
			$event_data,
			[
				'custom_data' => [
					'search_string' => '',
				],
			]
		);
	}
}
