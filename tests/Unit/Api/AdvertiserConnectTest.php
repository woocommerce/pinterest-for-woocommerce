<?php
/**
 * Class AdvertiserConnectTest
 *
 * @since 1.4.0
 */
declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Api;

use Automattic\WooCommerce\Pinterest\API\AdvertiserConnect;
use Exception;
use Pinterest_For_Woocommerce;
use WP_REST_Request;

/**
 * Tests connecting an advertiser and tag through the AdvertiserConnect API class.
 *
 * @version x.x.x
 */
class AdvertiserConnectTest extends \WP_UnitTestCase {

	/** A new Tag must update the integration even when its advertiser is unchanged. */
	public function test_existing_advertiser_connects_new_tag() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );
		Pinterest_For_Woocommerce::save_integration_data(
			array(
				'external_business_id'    => 'tag-fixture',
				'connected_advertiser_id' => '123',
				'connected_tag_id'        => '',
				'connected_merchant_id'   => 'preserve-merchant',
			)
		);
		$updates = array();
		add_filter(
			'pre_http_request',
			static function ( $response, $args, $url ) use ( &$updates ) {
				$body = array();
				if ( false !== strpos( $url, '/integrations/commerce/tag-fixture' ) ) {
					$updates[] = json_decode( $args['body'], true );
					$body      = array(
						'external_business_id'    => 'tag-fixture',
						'connected_advertiser_id' => '123',
						'connected_tag_id'        => '456',
					);
				}
				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( $body ),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
				);
			},
			10,
			3
		);
		$request = new WP_REST_Request( 'POST', '/pinterest/v1/tagowner' );
		$request->set_param( 'advrtsr_id', '123' );
		$request->set_param( 'tag_id', '456' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertTrue( $response->get_data()['reconnected'] );
		$this->assertSame(
			array(
				array(
					'connected_advertiser_id' => '123',
					'connected_tag_id'        => '456',
				),
			),
			$updates
		);
		$this->assertSame( '456', Pinterest_For_Woocommerce::get_data( 'integration_data' )['connected_tag_id'] );
		$this->assertSame( 'preserve-merchant', Pinterest_For_Woocommerce::get_data( 'integration_data' )['connected_merchant_id'] ?? null );
		$response = rest_get_server()->dispatch( $request );
		$this->assertFalse( $response->get_data()['reconnected'] );
		$this->assertCount( 1, $updates );
	}

	/** An incomplete success response must not erase the existing connection. */
	public function test_incomplete_update_keeps_saved_integration() {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$integration = array(
			'external_business_id'    => 'tag-fixture',
			'connected_advertiser_id' => '123',
			'connected_tag_id'        => '789',
		);
		Pinterest_For_Woocommerce::save_integration_data( $integration );
		add_filter(
			'pre_http_request',
			static function () {
				return array(
					'headers'  => array(),
					'body'     => '[]',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
				);
			}
		);
		$request = new WP_REST_Request( 'POST', '/pinterest/v1/tagowner' );
		$request->set_param( 'advrtsr_id', '123' );
		$request->set_param( 'tag_id', '456' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( $integration, Pinterest_For_Woocommerce::get_data( 'integration_data' ) );
		$this->assertSame( 400, $response->get_status() );
	}

	public function tearDown(): void {
		parent::tearDown();

		remove_all_filters( 'pre_http_request' );
	}

	public function test_connect_advertiser_and_tag_successfully_connects() {
		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				return array(
					'headers'  => array(
						'content-type' => 'application/json',
					),
					'body'     => json_encode(
						array(
							'id'                           => '987654321234567890',
							'external_business_id'         => 'cbi-1234567890',
							'connected_merchant_id'        => 'cmi-1234567890',
							'connected_user_id'            => 'cui-1234567890',
							'connected_advertiser_id'      => 'ai-1234567890',
							'connected_lba_id'             => 'cli-1234567890',
							'connected_tag_id'             => 'ti-1234567890',
							'partner_access_token_expiry'  => 1621350033000,
							'partner_refresh_token_expiry' => 1621350033000,
							'scopes'                       => 's-c-o-p-e-s',
							'created_timestamp'            => 1621350033000,
							'updated_timestamp'            => 1621350033300,
							'additional_id_1'              => 'ai1-1234567890',
							'partner_metadata'             => 'partner-meta-data',
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

		$output = AdvertiserConnect::connect_advertiser_and_tag(
			'ai-1234567890',
			'ti-1234567890'
		);

		$this->assertEquals(
			array(
				'connected'   => 'ai-1234567890',
				'reconnected' => true,
			),
			$output
		);
	}

	public function test_connect_advertiser_and_tag_returns_error() {
		$this->expectException( Exception::class );
		$this->expectExceptionCode( 400 );
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

		AdvertiserConnect::connect_advertiser_and_tag(
			'ai-1234567890',
			'ti-1234567890'
		);
	}
}
