<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\PinterestApiException;
use Automattic\WooCommerce\Pinterest\RefreshToken;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

class PinterestForWoocommerceTest extends WP_UnitTestCase {

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
}
