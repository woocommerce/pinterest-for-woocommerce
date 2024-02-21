<?php
/**
 * Class HealthTest
 *
 * @since x.x.x
 */
declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Api;

use WP_REST_Request;
use WP_Test_REST_TestCase;

class HealthTest extends WP_Test_REST_TestCase {

	/**
	 * Tests if the health route is registered.
	 *
	 * @return void
	 */
	public function test_tags_route_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/pinterest/v1/health', $routes );
	}

	/**
	 * Tests if the health endpoint rejects access.
	 *
	 * @return void
	 */
	public function test_tags_endpoint_rejects_access() {
		// 1. No authentication.
		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/health' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );

		// 2. No authorisation.
		$user = $this->factory->user->create( array( 'role' => 'guest' ) );
		wp_set_current_user( $user );

		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/health' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Tests if the health endpoint returns health status.
	 *
	 * @return void
	 */
	public function test_tags_endpoint_returns_advertiser_missing() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/health' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals(
			array(
				'status' => 'approved',
			),
			$response->get_data()
		);
	}
}
