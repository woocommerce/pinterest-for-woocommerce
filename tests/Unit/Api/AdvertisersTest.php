<?php
/**
 * Class AdvertisersTest.
 *
 * @since 1.4.0
 */
declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Api;

use Pinterest_For_Woocommerce;
use WP_REST_Request;
use WP_Test_REST_TestCase;

class AdvertisersTest extends WP_Test_REST_TestCase {

	public function tearDown(): void {
		parent::tearDown();

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Tests if the advertisers route is registered.
	 *
	 * @return void
	 */
	public function test_advertisers_route_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/pinterest/v1/tagowners', $routes );
	}

	/**
	 * Tests if advertisers endpoints rejects access.
	 *
	 * @return void
	 */
	public function test_advertisers_endpoint_rejects_access() {
		// 1. No authentication.
		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/tagowners' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );

		// 2. No authorisation.
		$user = $this->factory->user->create( array( 'role' => 'guest' ) );
		wp_set_current_user( $user );

		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/tagowners' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Tests advertisers endpoint returns the list of advertisers.
	 *
	 * @return void
	 */
	public function test_advertisers_endpoint_returns_the_list_of_advertisers() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		add_filter(
			'pre_http_request',
			function ( $response, $args, $url ) {
				if ( 'https://api.pinterest.com/v5/ad_accounts' === $url ) {
					$response = array(
						'headers' => array(
							'content-type' => 'application/json',
						),
						'body' => json_encode(
							array(
								'items' => array(
									array(
										'id'    => '1234567890',
										'name'  => 'Test Advertiser 0',
										'owner' => array(
											'username' => 'username_007',
										),
										'country'     => 'US',
										'currency'    => 'USD',
										'permissions' => 'some-permissions',
									),
									array(
										'id'    => '1234567891',
										'name'  => 'Test Advertiser 1',
										'owner' => array(
											'username' => 'username_008',
										),
										'country'     => 'US',
										'currency'    => 'USD',
										'permissions' => 'some-permissions',
									),
								),
							)
						),
						'response' => array(
							'code'    => 200,
							'message' => 'OK',
						),
						'cookies' => array(),
						'filename' => '',
					);
				}
				return $response;
			},
			10,
			3
		);

		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/tagowners' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals(
			array(
				'advertisers' => array(
					array(
						'id'   => '1234567890',
						'name' => 'Test Advertiser 0',
					),
					array(
						'id'   => '1234567891',
						'name' => 'Test Advertiser 1',
					),
				),
			),
			$response->get_data()
		);
	}
}
