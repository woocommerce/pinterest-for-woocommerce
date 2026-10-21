<?php
/**
 * Pinterest for WooCommerce API v5 base class tests.
 *
 * @since 1.4.0
 */
declare(strict_types=1);

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Api;

use Automattic\WooCommerce\Pinterest\API\APIV5;
use Automattic\WooCommerce\Pinterest\PinterestApiException;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

/**
 * Pinterest for WooCommerce API v5 base class tests.
 *
 * @version 1.6.0
 */
class APIV5Test extends WP_UnitTestCase {

	public function tearDown(): void {
		parent::tearDown();

		remove_all_filters( 'pre_http_request' );
	}

	public function test_create_tag_returns_successful_response() {
		add_filter(
			'pinterest_for_woocommerce_default_tag_name',
			function ( $tag_name ) {
				return 'Some tag name 42';
			}
		);
		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				$this->assertSame( 'https://api.pinterest.com/v5/ad_accounts/aai-1234567890/conversion_tags', $url );
				$this->assertSame( 'POST', $parsed_args['method'] );
				$this->assertTrue( $parsed_args['sslverify'] );
				$this->assertSame(
					array(
						'name'                    => 'Some tag name 42',
						'aem_enabled'             => true,
						'md_frequency'            => 1,
						'aem_fnln_enabled'        => true,
						'aem_ph_enabled'          => true,
						'aem_ge_enabled'          => true,
						'aem_db_enabled'          => true,
						'aem_loc_enabled'         => true,
						'aem_external_id_enabled' => true,
					),
					json_decode( $parsed_args['body'], true )
				);

				return array(
					'headers' => array(
						'content-type' => 'application/json',
					),
					'body' => json_encode(
						array(
							'ad_account_id'      => 'aai-1234567890',
							'code_snippet'       => '<!-- Pinterest Tag -->',
							'id'                 => '9876543210123456789',
							'last_fired_time_ms' => 123456789,
							'name'               => 'Some tag name 42',
							'status'             => 'ACTIVE',
							'version'            => 'v1',
							'configs' => array(
								'aem_enabled'             => true,
								'md_frequency'            => 1,
								'aem_fnln_enabled'        => true,
								'aem_ph_enabled'          => true,
								'aem_ge_enabled'          => true,
								'aem_db_enabled'          => true,
								'aem_loc_enabled'         => true,
								'aem_external_id_enabled' => true,
							),
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

		$output = APIV5::create_tag( 'aai-1234567890' );

		$this->assertEquals(
			'Some tag name 42',
			$output['name']
		);

		// Check if enhanced match is enabled.
		$this->assertEquals(
			array(
				'aem_enabled'             => true,
				'md_frequency'            => 1,
				'aem_fnln_enabled'        => true,
				'aem_ph_enabled'          => true,
				'aem_ge_enabled'          => true,
				'aem_db_enabled'          => true,
				'aem_loc_enabled'         => true,
				'aem_external_id_enabled' => true,
			),
			$output['configs']
		);
	}

	/**
	 * Transport errors propagate without an unverified retry.
	 */
	public function test_transport_error_does_not_retry_without_verification() {
		$calls = 0;
		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args ) use ( &$calls ) {
				++$calls;
				$this->assertTrue( $parsed_args['sslverify'] );
				return new \WP_Error( 'http_request_failed', 'Certificate verification failed.' );
			},
			10,
			2
		);

		try {
			APIV5::create_tag( 'aai-1234567890' );
			$this->fail( 'A failed request must not return a successful response.' );
		} catch ( PinterestApiException $e ) {
			$this->assertSame( 'Certificate verification failed.', $e->getMessage() );
			$this->assertSame( 1, $calls );
		}
	}

	public function test_create_tag_returns_unexpected_error() {
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

		APIV5::create_tag( 'aai-1234567890' );
	}

	/**
	 * A failed API call still triggers one reset and persists the disconnected state.
	 */
	public function test_any_api_call_401_response_calls_disconnect_action() {
		Pinterest_For_Woocommerce::set_default_settings();
		Pinterest_For_Woocommerce::save_token_data( array( 'access_token' => 'rejected-access-token' ) );
		Pinterest_For_Woocommerce::save_setting( 'tracking_advertiser', 'aai-1234567890' );
		Pinterest_For_Woocommerce::save_setting( 'tracking_tag', 'rejected-account-tag' );
		Pinterest_For_Woocommerce::save_setting( 'enable_debug_logging', true );
		Pinterest_For_Woocommerce::save_setting(
			'account_data',
			array(
				'id'         => '123456789',
				'is_partner' => true,
			)
		);
		$this->assertTrue( Pinterest_For_Woocommerce::is_business_connected() );
		$before   = did_action( 'pinterest_for_woocommerce_disconnect' );
		$requests = array();
		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) use ( &$requests ) {
				$requests[] = array( $parsed_args['method'], $url );
				return array(
					'headers'  => array( 'content-type' => 'application/json' ),
					'body'     => wp_json_encode(
						array(
							'code'    => 2,
							'message' => 'Authentication failed.',
						)
					),
					'response' => array(
						'code'    => 401,
						'message' => 'Unauthorized',
					),
					'cookies'  => array(),
					'filename' => '',
				);
			},
			10,
			3
		);
		$exception = null;
		try {
			APIV5::create_tag( 'aai-1234567890' );
		} catch ( PinterestApiException $error ) {
			$exception = $error;
		}
		$this->assertInstanceOf( PinterestApiException::class, $exception );
		$this->assertSame( 401, $exception->getCode() );
		$this->assertSame( 'Authentication failed.', $exception->getMessage() );
		$this->assertSame( array( array( 'POST', 'https://api.pinterest.com/v5/ad_accounts/aai-1234567890/conversion_tags' ) ), $requests );
		$this->assertSame( $before + 1, did_action( 'pinterest_for_woocommerce_disconnect' ) );
		$this->assertFalse( get_option( PINTEREST_FOR_WOOCOMMERCE_DATA_NAME ) );
		$this->assertFalse( Pinterest_For_Woocommerce::is_business_connected() );
		$this->assertFalse( Pinterest_For_Woocommerce::get_setting( 'tracking_advertiser', true ) );
		$this->assertFalse( Pinterest_For_Woocommerce::get_setting( 'tracking_tag', true ) );
		$this->assertSame( array( 'currency_credit_info' ), array_keys( Pinterest_For_Woocommerce::get_setting( 'account_data', true ) ) );
		$this->assertTrue( Pinterest_For_Woocommerce::get_setting( 'enable_debug_logging', true ) );
	}
}
