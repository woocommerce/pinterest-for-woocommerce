<?php

namespace Automattic\WooCommerce\Pinterest\Tracking;

use Automattic\WooCommerce\Pinterest\API\APIV5;
use Automattic\WooCommerce\Pinterest\Tracking;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Category;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Checkout;
use Automattic\WooCommerce\Pinterest\Tracking\Data\None;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Product;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Search;
use Automattic\WooCommerce\Pinterest\Tracking\Data\User;
use Throwable;

class Conversions implements Tracker {

	const EVENT_MAP = array(
		Tracking::EVENT_PAGE_VISIT    => 'page_visit',
		Tracking::EVENT_SEARCH        => 'search',
		Tracking::EVENT_VIEW_CATEGORY => 'view_category',
		Tracking::EVENT_ADD_TO_CART   => 'add_to_cart',
		Tracking::EVENT_CHECKOUT      => 'checkout',
	);

	private User $user;

	public function __construct( User $user ) {
		$this->user = $user;
	}

	public function track_event( string $event_name, Data $data ) {
		global $wp;

		if ( Tracking::EVENT_SEARCH === $event_name ) {
			/** @var Search $data */
			$data = array(
				'event_id'    => $data->get_event_id(),
				'custom_data' => array(
					'search_string' => $data->get_search_query(),
				),
			);
		}

		if ( Tracking::EVENT_PAGE_VISIT === $event_name && ! ( $data instanceof None ) ) {
			/** @var Product $data */
			$data = array(
				'event_id'    => $data->get_event_id(),
				'custom_data' => array(
					'currency'    => $data->get_currency(),
					'value'       => $data->get_price() * $data->get_quantity(),
					'content_ids' => array( $data->get_id() ),
					'contents'    => array(
						array(
							'id'         => $data->get_id(),
							'item_price' => $data->get_price(),
							'quantity'   => $data->get_quantity(),
						),
					),
					'num_items'   => $data->get_quantity(),
				),
			);
		}

		if ( Tracking::EVENT_VIEW_CATEGORY === $event_name ) {
			/** @var Category $data */
			$data = array(
				'event_id'    => $data->get_event_id(),
				'custom_data' => array(
					'category_name' => $data->getCategoryName(),
				),
			);
		}

		if ( Tracking::EVENT_ADD_TO_CART === $event_name ) {
			/** @var Product $data */
			$data = array(
				'event_id'    => $data->get_event_id(),
				'custom_data' => array(
					'currency'    => $data->get_currency(),
					'value'       => $data->get_price() * $data->get_quantity(),
					'content_ids' => array( $data->get_id() ),
					'contents'    => array(
						array(
							'id'         => $data->get_id(),
							'item_price' => $data->get_price(),
							'quantity'   => $data->get_quantity(),
						),
					),
					'num_items'   => $data->get_quantity(),
				),
			);
		}

		if ( Tracking::EVENT_CHECKOUT === $event_name ) {
			/** @var Checkout $data */
			$data = array(
				'event_id'    => $data->get_event_id(),
				'custom_data' => array(
					'currency'    => $data->get_currency(),
					'value'       => $data->get_price() * $data->get_quantity(),
					'content_ids' => array_map(
						function ( Product $product ) {
							return $product->get_id();
						},
						$data->get_items()
					),
					'contents'    => array_map(
						function ( Product $product ) {
							return array(
								'id'         => $product->get_id(),
								'item_price' => $product->get_price(),
								'quantity'   => $product->get_quantity(),
							);
						},
						$data->get_items()
					),
					'num_items'   => $data->get_quantity(),
				),
			);
		}

		if ( $data instanceof None ) {
			$data = array(
				'event_id' => $data->get_event_id(),
			);
		}

		$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
		$event_name    = static::EVENT_MAP[ $event_name ] ?? '';

		/** @var array $data */
		$data = array_merge(
			$data,
			array(
				'event_name'       => $event_name,
				'action_source'    => 'web',
				'event_time'       => time(),
				'event_source_url' => home_url( $wp->request ),
				'partner_name'     => 'ss-woocommerce',
				'user_data'        => array(
					'client_ip_address' => $this->user->get_client_ip_address(),
					'client_user_agent' => $this->user->get_client_user_agent(),
				),
				'language'         => 'en',
			)
		);

		try {
			APIV5::make_request(
				"ad_accounts/{$ad_account_id}/events",
				'POST',
				array( 'data' => array( $data ) )
			);
		} catch ( Throwable $e ) {
			// Do nothing.
		}
	}

	/**
	 * @return bool
	 */
	public static function is_conversions_api_enabled() {
		return true;
	}
}
