<?php

namespace Automattic\WooCommerce\Pinterest\Tracking;

use Automattic\WooCommerce\Pinterest\Tracking;
use Automattic\WooCommerce\Pinterest\Tracking\Data\User;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

class ConversionsTest extends WP_UnitTestCase {

	public function test_conversions_track_page_visit_event() {
		Pinterest_For_Woocommerce::save_settings(
			array(
				'tracking_advertiser' => 'PFW-123456789',
			)
		);

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				$this->assertEquals(
					"https://api.pinterest.com/v5/ad_accounts/PFW-123456789/events",
					$url
				);
				$this->assertEquals( 'POST', $parsed_args['method'] );

				$body = json_decode( $parsed_args['body'], true );
				// Time is dynamic, so we unset it.
				unset( $body['data'][0]['event_time'] );

				$this->assertEquals(
					array(
						'data' => array(
							array(
								'event_id'         => 'event-id-123',
								'event_name'       => 'page_visit',
								'action_source'    => 'web',
								'event_source_url' => 'http://example.org',
								'partner_name'     => 'ss-woocommerce',
								'user_data'        => array(
									'client_ip_address' => 'Some IP address.',
									'client_user_agent' => 'Some user agent string.',
								),
								'language'         => 'en',
							),
						),
					),
					$body
				);

				// Returning dummy but success response.
				return array(
					'headers'  => array(
						'content-type' => 'application/json',
					),
					'body'     => '',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => '',
				);
			},
			10,
			3
		);

		$user        = new User( 'Some IP address.', 'Some user agent string.' );
		$conversions = new Conversions( $user );
		$conversions->track_event( Tracking::EVENT_PAGE_VISIT, new Data\None( 'event-id-123' ) );
	}

	public function test_get_checkout_data() {
		$user        = new User( 'Some IP address.', 'Some user agent string.' );
		$conversions = new Conversions( $user );

		$checkout = new Tracking\Data\Checkout(
			'event-id-123',
			'1234567890',
			'29.97',
			3,
			'USD',
			array(
				new Tracking\Data\Product( 'eid1', 1, 'Name 1', 'Furniture', 'Brand', '9.99', 'USD', 1 ),
				new Tracking\Data\Product( 'eid2', 2, 'Name 2', 'Furniture', 'Brand', '9.99', 'USD', 1 ),
				new Tracking\Data\Product( 'eid3', 3, 'Name 3', 'Accessories', 'Brand', '9.99', 'USD', 1 ),
			)
		);

		$data = $conversions->prepare_request_data( Tracking::EVENT_CHECKOUT, $checkout );

		// Remove event time data on purposes since it is dynamic and we can not test it.
		unset( $data['event_time'] );

		$this->assertEquals(
			array(
				'event_id'         => 'event-id-123',
				'event_name'       => 'checkout',
				'action_source'    => 'web',
				'event_source_url' => 'http://example.org',
				'partner_name'     => 'ss-woocommerce',
				'user_data'        => array(
					'client_ip_address' => 'Some IP address.',
					'client_user_agent' => 'Some user agent string.',
				),
				'custom_data'      => array(
					'currency'    => 'USD',
					'value'       => '29.97',
					'content_ids' => array( 1, 2, 3 ),
					'contents'    => array(
						array( 'id' => 1, 'item_price' => '9.99', 'quantity' => 1 ),
						array( 'id' => 2, 'item_price' => '9.99', 'quantity' => 1 ),
						array( 'id' => 3, 'item_price' => '9.99', 'quantity' => 1 ),
					),
					'num_items'   => 3,
				),
				'language'         => 'en',
			),
			$data
		);
	}

	public function test_get_add_to_cart_data() {
		$user        = new User( 'Some IP address.', 'Some user agent string.' );
		$conversions = new Conversions( $user );

		$product = new Tracking\Data\Product( 'event-id-321', 1, 'Name 1', 'Furniture', 'Brand', '9.99', 'USD', 2 );

		$data = $conversions->prepare_request_data( Tracking::EVENT_ADD_TO_CART, $product );

		// Remove event time data on purposes since it is dynamic and we can not test it.
		unset( $data['event_time'] );

		$this->assertEquals(
			array(
				'event_id'         => 'event-id-321',
				'event_name'       => 'add_to_cart',
				'action_source'    => 'web',
				'event_source_url' => 'http://example.org',
				'partner_name'     => 'ss-woocommerce',
				'user_data'        => array(
					'client_ip_address' => 'Some IP address.',
					'client_user_agent' => 'Some user agent string.',
				),
				'custom_data'      => array(
					'currency'    => 'USD',
					'value'       => '19.98',
					'content_ids' => array( 1 ),
					'contents'    => array(
						array( 'id' => 1, 'item_price' => '9.99', 'quantity' => 2 ),
					),
					'num_items'   => 2,
				),
				'language'         => 'en',
			),
			$data
		);
	}

	public function test_get_view_category_data() {
		$user        = new User( 'Some IP address.', 'Some user agent string.' );
		$conversions = new Conversions( $user );

		$category = new Tracking\Data\Category( 'event-id-312', 1, 'Category 1' );

		$data = $conversions->prepare_request_data( Tracking::EVENT_VIEW_CATEGORY, $category );

		// Remove event time data on purposes since it is dynamic and we can not test it.
		unset( $data['event_time'] );

		$this->assertEquals(
			array(
				'event_id'         => 'event-id-312',
				'event_name'       => 'view_category',
				'action_source'    => 'web',
				'event_source_url' => 'http://example.org',
				'partner_name'     => 'ss-woocommerce',
				'user_data'        => array(
					'client_ip_address' => 'Some IP address.',
					'client_user_agent' => 'Some user agent string.',
				),
				'custom_data'      => array(
					'category_name'    => 'Category 1',
				),
				'language'         => 'en',
			),
			$data
		);
	}

	public function test_get_page_visit_data() {
		$user        = new User( 'Some IP address.', 'Some user agent string.' );
		$conversions = new Conversions( $user );

		$product = new Tracking\Data\Product( 'event-id-132', 1, 'Name 1', 'Furniture', 'Brand', '9.99', 'USD', 1 );

		$data = $conversions->prepare_request_data( Tracking::EVENT_PAGE_VISIT, $product );

		// Remove event time data on purposes since it is dynamic and we can not test it.
		unset( $data['event_time'] );

		$this->assertEquals(
			array(
				'event_id'         => 'event-id-132',
				'event_name'       => 'page_visit',
				'action_source'    => 'web',
				'event_source_url' => 'http://example.org',
				'partner_name'     => 'ss-woocommerce',
				'user_data'        => array(
					'client_ip_address' => 'Some IP address.',
					'client_user_agent' => 'Some user agent string.',
				),
				'custom_data'      => array(
					'currency'    => 'USD',
					'value'       => '9.99',
					'content_ids' => array( 1 ),
					'contents'    => array(
						array( 'id' => 1, 'item_price' => '9.99', 'quantity' => 1 ),
					),
					'num_items'   => 1,
				),
				'language'         => 'en',
			),
			$data
		);
	}

	public function test_get_search_data() {
		$user        = new User( 'Some IP address.', 'Some user agent string.' );
		$conversions = new Conversions( $user );

		$search = new Tracking\Data\Search( 'event-id-111', 'Search query string...' );

		$data = $conversions->prepare_request_data( Tracking::EVENT_SEARCH, $search );

		// Remove event time data on purposes since it is dynamic and we can not test it.
		unset( $data['event_time'] );

		$this->assertEquals(
			array(
				'event_id'         => 'event-id-111',
				'event_name'       => 'search',
				'action_source'    => 'web',
				'event_source_url' => 'http://example.org',
				'partner_name'     => 'ss-woocommerce',
				'user_data'        => array(
					'client_ip_address' => 'Some IP address.',
					'client_user_agent' => 'Some user agent string.',
				),
				'custom_data'      => array(
					'search_string' => 'Search query string...',
				),
				'language'         => 'en',
			),
			$data
		);
	}
}
