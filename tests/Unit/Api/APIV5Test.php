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

class APIV5Test extends WP_UnitTestCase {

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
								'aem_enabled'      => true,
								'md_frequency'     => 1,
								'aem_fnln_enabled' => true,
								'aem_ph_enabled'   => true,
								'aem_ge_enabled'   => true,
								'aem_db_enabled'   => true,
								'ae_loc_enabled'   => true,
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
				'aem_enabled'      => true,
				'md_frequency'     => 1,
				'aem_fnln_enabled' => true,
				'aem_ph_enabled'   => true,
				'aem_ge_enabled'   => true,
				'aem_db_enabled'   => true,
				'ae_loc_enabled'   => true,
			),
			$output['configs']
		);
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

	public function test_any_api_call_401_response_calls_disconnect_action() {
		$this->expectException( PinterestApiException::class );
		$this->expectExceptionCode( 401 );
		$this->expectExceptionMessage( 'Authentication failed.' );

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				return array(
					'headers'  => array(
						'content-type' => 'application/json',
					),
					'body'     => json_encode(
						array(
							'code'    => 2,
							'message' => 'Authentication failed.',
						)
					),
					'response' => array(
						'code'    => 401,
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

		$this->assertEquals( 1, did_action( 'pinterest_for_woocommerce_disconnect' ) );
	}
}
