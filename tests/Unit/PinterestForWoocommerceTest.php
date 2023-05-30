<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\PinterestApiException;
use Automattic\WooCommerce\Pinterest\RefreshToken;
use Pinterest_For_Woocommerce;
use WP_HTTP_Requests_Response;
use WP_UnitTestCase;
use WpOrg\Requests\Response;

class PinterestForWoocommerceTest extends WP_UnitTestCase {

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
						'code' => 200,
					),
					'cookies'  => array(),
					'filename' => '',
					'http_response' => new WP_HTTP_Requests_Response(
						new Response(),
						''
					),
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
						'code' => 404,
					),
					'cookies'  => array(),
					'filename' => '',
					'http_response' => new WP_HTTP_Requests_Response(
						new Response(),
						''
					),
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
						'code' => 409,
					),
					'cookies'  => array(),
					'filename' => '',
					'http_response' => new WP_HTTP_Requests_Response(
						new Response(),
						''
					),
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
		$this->expectExceptionCode( 0 );
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
						'code' => 0,
					),
					'cookies'  => array(),
					'filename' => '',
					'http_response' => new WP_HTTP_Requests_Response(
						new Response(),
						''
					),
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
