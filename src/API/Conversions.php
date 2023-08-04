<?php
/**
 * @package Pinterest_For_Woocommerce/API
 */
namespace Automattic\WooCommerce\Pinterest\API;

use Automattic\WooCommerce\Pinterest\API\Conversions\CartData;
use Automattic\WooCommerce\Pinterest\API\Conversions\CustomData;
use Automattic\WooCommerce\Pinterest\API\Conversions\EventIdProvider;
use Automattic\WooCommerce\Pinterest\API\Conversions\SearchData;
use Automattic\WooCommerce\Pinterest\API\Conversions\UserData;
use Exception;

class Conversions {

	private UserData $user_data;

	/**
	 * @var CartData|SearchData $custom_data
	 */
	private CustomData $custom_data;

	public function __construct( UserData $user_data, CustomData $custom_data ) {
		$this->user_data   = $user_data;
		$this->custom_data = $custom_data;
	}

	/**
	 * An array of event names to event IDs map for the Pinterest Conversions API and Pinterest Tag.
	 *
	 * @since x.x.x
	 *
	 * @param string $event_name
	 * @param array $data
	 */
	public function add_event( string $event_name, array $data = array() ) {
		$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );

		$data = array_merge(
			$data,
			array(
				'ad_account_id' => $ad_account_id,
				'event_name'    => $event_name,
				'action_source' => 'website',
				'event_time'    => time(),
				'user_data'     => array(
					'client_ip_address' => $this->user_data->get_client_ip_address(),
					'client_user_agent' => $this->user_data->get_client_user_agent(),
				),
				'event_id'         => EventIdProvider::get_event_id( $event_name ),
				'event_source_url' => '',
			)
		);

		$conversions_event_name = EventIdProvider::get_event_name_by_pinterest_tag_event_name( $event_name );
		if ( in_array( $conversions_event_name, array( 'add_to_cart', 'checkout', 'purchase' ), true ) ) {
			$data = $this->add_cart_and_checkout_custom_data( $data );
		}

		if ( in_array( $conversions_event_name, array( 'search', 'view_search_results' ), true ) ) {
			$data = $this->add_search_custom_data( $data );
		}

		try {
			APIV5::make_request(
				"ad_accounts/{$ad_account_id}/events",
				'POST',
				$data
			);
		} catch ( Exception $e ) {
			// Do nothing.
		}
	}

	/**
	 * Adds custom data related to add to cart and checkout events.
	 *
	 * @since x.x.x
	 * @link https://developers.pinterest.com/docs/conversions/best/#Required#Custom%20data%20object
	 *
	 * @param array $event_data
	 * @return array|string[][]
	 */
	private function add_cart_and_checkout_custom_data( array $event_data ): array {
		return array_merge(
			$event_data,
			array(
				'custom_data' => array(
					'currency'    => $this->custom_data->get_currency(),
					'value'       => $this->custom_data->get_value(),
					'content_ids' => $this->custom_data->get_content_ids(),
					'contents'    => $this->custom_data->get_contents(),
					'num_items'   => $this->custom_data->get_num_items(),
					'order_id'    => $this->custom_data->get_order_id(),
				),
			)
		);
	}

	/**
	 * Adds custom data related to search events.
	 *
	 * @since x.x.x
	 *
	 * @param array $event_data
	 * @return array
	 */
	private function add_search_custom_data( array $event_data ): array {
		return array_merge(
			$event_data,
			array(
				'custom_data' => array(
					'search_string' => $this->custom_data->get_search_string(),
				),
			)
		);
	}
}
