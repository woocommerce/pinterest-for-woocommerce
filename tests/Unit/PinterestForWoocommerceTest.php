<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\Heartbeat;
use Automattic\WooCommerce\Pinterest\PinterestApiException;
use Automattic\WooCommerce\Pinterest\RefreshToken;
use Automattic\WooCommerce\Pinterest\Tracking\Conversions;
use Automattic\WooCommerce\Pinterest\Tracking\Tag;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

class PinterestForWoocommerceTest extends WP_UnitTestCase {

	public function tearDown(): void {
		parent::tearDown();

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Tests default settings are set.
	 *
	 * @return void
	 */
	public function test_set_default_settings() {
		// Make sure settings are empty.
		Pinterest_For_Woocommerce::save_settings( array() );

		Pinterest_For_Woocommerce::set_default_settings();

		$settings = Pinterest_For_Woocommerce::get_settings( true );
		$this->assertEquals(
			array(
				'track_conversions'                => true,
				'track_conversions_capi'           => false,
				'enhanced_match_support'           => true,
				'automatic_enhanced_match_support' => true,
				'save_to_pinterest'                => true,
				'rich_pins_on_posts'               => true,
				'rich_pins_on_products'            => true,
				'product_sync_enabled'             => true,
				'enable_debug_logging'             => false,
				'erase_plugin_data'                => false,
			),
			$settings
		);
	}

	/**
	 * Test of the plugin has disconnect action initialised.
	 *
	 * @return void
	 */
	public function test_disconnect_events_added_with_plugin() {
		Pinterest_For_Woocommerce();
		$this->assertEquals(
			10,
			has_action(
				'pinterest_for_woocommerce_disconnect',
				[ Pinterest_For_Woocommerce::class, 'reset_connection' ]
			)
		);
		$this->assertEquals(
			10,
			has_action(
				'action_scheduler_failed_execution',
				[ Pinterest_For_Woocommerce::class, 'action_scheduler_reset_connection' ]
			)
		);
	}

	/**
	 * Test of the plugin has refresh token action initialised.
	 *
	 * @return void
	 */
	public function test_pinterest_api_access_token_scheduled_for_refresh() {
		Pinterest_For_Woocommerce();
		$this->assertEquals( 10, has_action( 'init', [ RefreshToken::class, 'schedule_event' ] ) );
	}

	public function test_update_commerce_integration_returns_successful_response() {
		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				return array(
					'headers'  => array(
						'content-type' => 'application/json',
					),
					'body'     => json_encode(
						array_merge(
							array(
								'id'                => '9876543210123456789',
								'connected_user_id' => 'cud-123456789',
								'created_timestamp' => 123456789,
								'updated_timestamp' => 987654321,
							),
							array_filter(
								json_decode( $parsed_args['body'], true ),
								function ( $key ) {
									return ! in_array(
										$key,
										array( 'partner_access_token', 'partner_refresh_token',  'partner_primary_email' )
									);
								},
								ARRAY_FILTER_USE_KEY
							)
						)
					),
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

		$external_business_id = 'ebi-123456789';
		$data                 = array(
			'external_business_id'         => 'ebi-123456789',
			'connected_merchant_id'        => 'cmd-123456789',
			'connected_advertiser_id'      => 'cai-123456789',
			'connected_lba_id'             => 'cli-123456789',
			'connected_tag_id'             => 'cti-123456789',
			'partner_access_token'         => 'pat-123456789',
			'partner_refresh_token'        => 'prt-123456789',
			'partner_primary_email'        => 'ppe-123456789',
			'partner_access_token_expiry'  => 9876543210,
			'partner_refresh_token_expiry' => 9876543210,
			'scopes'                       => 's-c-o-p-e-s',
			'additional_id_1'              => 'ai1-123456789',
			'partner_metadata'             => 'partner-meta-data',
		);
		$response = Pinterest_For_Woocommerce::update_commerce_integration( $external_business_id, $data );

		$this->assertEquals(
			array(
				'id'                           => '9876543210123456789',
				'external_business_id'         => 'ebi-123456789',
				'connected_merchant_id'        => 'cmd-123456789',
				'connected_user_id'            => 'cud-123456789',
				'connected_advertiser_id'      => 'cai-123456789',
				'connected_lba_id'             => 'cli-123456789',
				'connected_tag_id'             => 'cti-123456789',
				'partner_access_token_expiry'  => 9876543210,
				'partner_refresh_token_expiry' => 9876543210,
				'scopes'                       => 's-c-o-p-e-s',
				'created_timestamp'            => 123456789,
				'updated_timestamp'            => 987654321,
				'additional_id_1'              => 'ai1-123456789',
				'partner_metadata'             => 'partner-meta-data',
			),
			$response
		);
	}

	public function test_update_commerce_integration_returns_integration_not_found() {
		$this->expectException( PinterestApiException::class );
		$this->expectExceptionCode( 404 );
		$this->expectExceptionMessage( 'Sorry! We could not find your integration.' );

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				return array(
					'headers'  => array(
						'content-type' => 'application/json',
					),
					'body'     => json_encode(
						array(
							'code'    => 4180,
							'message' => 'Sorry! We could not find your integration.',
						)
					),
					'response' => array(
						'code'    => 404,
						'message' => 'Not Found',
					),
					'cookies'  => array(),
					'filename' => '',
				);
			},
			10,
			3
		);

		$data = array(
			'external_business_id'         => 'ebi-123456789',
			'connected_merchant_id'        => 'cmd-123456789',
			'connected_advertiser_id'      => 'cai-123456789',
			'connected_lba_id'             => 'cli-123456789',
			'connected_tag_id'             => 'cti-123456789',
			'partner_access_token'         => 'pat-123456789',
			'partner_refresh_token'        => 'prt-123456789',
			'partner_primary_email'        => 'ppe-123456789',
			'partner_access_token_expiry'  => 9876543210,
			'partner_refresh_token_expiry' => 9876543210,
			'scopes'                       => 's-c-o-p-e-s',
			'additional_id_1'              => 'ai1-123456789',
			'partner_metadata'             => 'partner-meta-data',
		);
		Pinterest_For_Woocommerce::update_commerce_integration( 'ebi-123456789', $data );
	}

	public function test_update_commerce_integration_returns_cant_access_this_integration_metadata() {
		$this->expectException( PinterestApiException::class );
		$this->expectExceptionCode( 409 );
		$this->expectExceptionMessage( 'Can\'t access this integration metadata.' );

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				return array(
					'headers'  => array(
						'content-type' => 'application/json',
					),
					'body'     => json_encode(
						array(
							'code'    => 4182,
							'message' => 'Can\'t access this integration metadata.',
						)
					),
					'response' => array(
						'code'    => 409,
						'message' => 'Conflict',
					),
					'cookies'  => array(),
					'filename' => '',
				);
			},
			10,
			3
		);

		$data = array(
			'external_business_id'         => 'ebi-123456789',
			'connected_merchant_id'        => 'cmd-123456789',
			'connected_advertiser_id'      => 'cai-123456789',
			'connected_lba_id'             => 'cli-123456789',
			'connected_tag_id'             => 'cti-123456789',
			'partner_access_token'         => 'pat-123456789',
			'partner_refresh_token'        => 'prt-123456789',
			'partner_primary_email'        => 'ppe-123456789',
			'partner_access_token_expiry'  => 9876543210,
			'partner_refresh_token_expiry' => 9876543210,
			'scopes'                       => 's-c-o-p-e-s',
			'additional_id_1'              => 'ai1-123456789',
			'partner_metadata'             => 'partner-meta-data',
		);
		Pinterest_For_Woocommerce::update_commerce_integration( 'ebi-123456789', $data );
	}

	public function test_update_commerce_integration_returns_unexpected_error() {
		$this->expectException( PinterestApiException::class );
		$this->expectExceptionCode( 500 );
		$this->expectExceptionMessage( 'Any other message from Pinterest which falls under Unexpected error.' );

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				return array(
					'headers'  => array(
						'content-type' => 'application/json',
					),
					'body'     => json_encode(
						array(
							'code'    => 0,
							'message' => 'Any other message from Pinterest which falls under Unexpected error.',
						)
					),
					'response' => array(
						'code'    => 500,
						'message' => 'Unexpected error',
					),
					'cookies'  => array(),
					'filename' => '',
				);
			},
			10,
			3
		);

		$data = array(
			'external_business_id'         => 'ebi-123456789',
			'connected_merchant_id'        => 'cmd-123456789',
			'connected_advertiser_id'      => 'cai-123456789',
			'connected_lba_id'             => 'cli-123456789',
			'connected_tag_id'             => 'cti-123456789',
			'partner_access_token'         => 'pat-123456789',
			'partner_refresh_token'        => 'prt-123456789',
			'partner_primary_email'        => 'ppe-123456789',
			'partner_access_token_expiry'  => 9876543210,
			'partner_refresh_token_expiry' => 9876543210,
			'scopes'                       => 's-c-o-p-e-s',
			'additional_id_1'              => 'ai1-123456789',
			'partner_metadata'             => 'partner-meta-data',
		);
		Pinterest_For_Woocommerce::update_commerce_integration( 'ebi-123456789', $data );
	}

	public function test_disconnect_removes_as_daily_and_hourly_actions() {
		$heartbeat = new Heartbeat( WC()->queue() );
		$heartbeat->schedule_events();
		Pinterest_For_Woocommerce::disconnect();
		$this->assertFalse( as_has_scheduled_action( Heartbeat::HOURLY, array(), 'pinterest-for-woocommerce' ) );
		$this->assertFalse( as_has_scheduled_action( Heartbeat::DAILY, array(), 'pinterest-for-woocommerce' ) );
	}

	public function test_init_tracking_inits_if_at_least_one_tracker() {
		Pinterest_For_Woocommerce::save_setting( 'track_conversions', true );

		$tracking = Pinterest_For_Woocommerce::init_tracking();

		$this->assertEquals( 10, has_action( 'wp_footer', array( $tracking, 'handle_page_visit' ) ) );
		$this->assertEquals( 10, has_action( 'wp_footer', array( $tracking, 'handle_view_category' ) ) );
		$this->assertEquals( 10, has_action( 'woocommerce_add_to_cart', array( $tracking, 'handle_add_to_cart' ) ) );
		$this->assertEquals( 10, has_action( 'woocommerce_before_thankyou', array( $tracking, 'handle_checkout' ) ) );
		$this->assertEquals( 10, has_action( 'wp_footer', array( $tracking, 'handle_search' ) ) );
	}

	public function test_init_tracking_does_not_init_if_no_trackers() {
		$tracking = Pinterest_For_Woocommerce::init_tracking();

		$this->assertFalse( has_action( 'wp_footer', array( $tracking, 'handle_page_visit' ) ) );
		$this->assertFalse( has_action( 'wp_footer', array( $tracking, 'handle_view_category' ) ) );
		$this->assertFalse( has_action( 'woocommerce_add_to_cart', array( $tracking, 'handle_add_to_cart' ) ) );
		$this->assertFalse( has_action( 'woocommerce_before_thankyou', array( $tracking, 'handle_checkout' ) ) );
		$this->assertFalse( has_action( 'wp_footer', array( $tracking, 'handle_search' ) ) );
	}

	public function test_init_tracking_inits_tag_tracker() {
		Pinterest_For_Woocommerce::save_setting( 'track_conversions', true );
		Pinterest_For_Woocommerce::save_setting( 'track_conversions_capi', false );

		$tracking = Pinterest_For_Woocommerce::init_tracking();

		$trackers = $tracking->get_trackers();

		$this->assertCount( 1, $trackers );
		$this->assertInstanceOf( Tag::class, current( $trackers ) );
	}

	public function test_init_tracking_does_not_init_capi_tracker_if_tag_tracker_disabled() {
		Pinterest_For_Woocommerce::save_setting( 'track_conversions', false );
		Pinterest_For_Woocommerce::save_setting( 'track_conversions_capi', true );

		$tracking = Pinterest_For_Woocommerce::init_tracking();
		$this->assertFalse( $tracking );
	}

	public function test_init_tracking_inits_no_trackers() {
		Pinterest_For_Woocommerce::save_setting( 'track_conversions', false );
		Pinterest_For_Woocommerce::save_setting( 'track_conversions_capi', false );

		$tracking = Pinterest_For_Woocommerce::init_tracking();

		$this->assertFalse( $tracking );
	}

	public function test_init_tracking_inits_both_trackers() {
		Pinterest_For_Woocommerce::save_setting( 'track_conversions', true );
		Pinterest_For_Woocommerce::save_setting( 'track_conversions_capi', true );

		$tracking = Pinterest_For_Woocommerce::init_tracking();

		$trackers = $tracking->get_trackers();

		$this->assertCount( 2, $trackers );
		$this->assertInstanceOf( Tag::class, array_shift( $trackers ) );
		$this->assertInstanceOf( Conversions::class, array_shift( $trackers ) );
	}
}
