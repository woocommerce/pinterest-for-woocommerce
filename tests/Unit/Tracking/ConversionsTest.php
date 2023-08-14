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

	public function test_get_default_data() {
		$user        = new User( 'Some IP address.', 'Some user agent string.' );
		$conversions = new Conversions( $user );
		$data        = $conversions->get_default_data( 'some_dummy_event_name' );
		unset( $data['event_time'] ); // Time is dynamic, so we unset it.

		$this->assertEquals(
			array(
				'event_name'       => 'some_dummy_event_name',
				'action_source'    => 'web',
				'event_source_url' => 'http://example.org',
				'partner_name'     => 'ss-woocommerce',
				'user_data'        => array(
					'client_ip_address' => 'Some IP address.',
					'client_user_agent' => 'Some user agent string.',
				),
				'language'         => 'en',
			),
			$data
		);
	}

	public function test_get_checkout_data() {
		$user        = new User( 'Some IP address.', 'Some user agent string.' );
		$conversions = new Conversions( $user );

		$checkout    = new Tracking\Data\Checkout(
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

		$data = $conversions->get_checkout_data( $checkout );

		$this->assertEquals(
			array(
				'event_id'    => 'event-id-123',
				'custom_data' => array(
					'currency'    => 'USD',
					'value'       => '29.97',
					'content_ids' => array( 1, 2, 3 ),
					'contents'    => array(
						array( 'id' => 1, 'item_price' => '9.99', 'quantity' => 1 ),
						array( 'id' => 2, 'item_price' => '9.99', 'quantity' => 1 ),
						array( 'id' => 3, 'item_price' => '9.99', 'quantity' => 1 ),
					),
					'num_items' => 3,
				),
			),
			$data
		);
	}
}
