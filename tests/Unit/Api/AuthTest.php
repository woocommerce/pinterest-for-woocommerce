<?php
/**
 * Class AuthTest
 *
 * Covers the OAuth callback state validation lifecycle.
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Api;

use RuntimeException;
use WP_REST_Request;
use WP_Test_REST_TestCase;

/**
 * Tests for the OAuth callback endpoint.
 */
class AuthTest extends WP_Test_REST_TestCase {

	private const ROUTE = '/pinterest/v1/oauth/callback';

	/**
	 * Clears any issued state before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		delete_transient( PINTEREST_FOR_WOOCOMMERCE_CONNECT_NONCE );
	}

	/**
	 * Clears issued state and redirect hooks after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();
		delete_transient( PINTEREST_FOR_WOOCOMMERCE_CONNECT_NONCE );
		remove_all_filters( 'wp_redirect' );
	}

	/**
	 * Tests the callback route is registered.
	 *
	 * @return void
	 */
	public function test_callback_route_registered() {
		$this->assertArrayHasKey( self::ROUTE, rest_get_server()->get_routes() );
	}

	/**
	 * Tests the callback rejects a request when no state has been issued.
	 *
	 * @return void
	 */
	public function test_callback_rejects_request_when_no_state_issued() {
		$response = rest_get_server()->dispatch( $this->request() );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Tests the callback rejects a non-string state when no state has been issued.
	 *
	 * @return void
	 */
	public function test_callback_rejects_non_string_state_when_no_state_issued() {
		$request = $this->request();
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'state' => false ) ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Tests the callback rejects a request without a state when one has been issued.
	 *
	 * @return void
	 */
	public function test_callback_rejects_missing_state_when_state_issued() {
		$this->issue_state();

		$response = rest_get_server()->dispatch( $this->request() );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Tests the callback rejects a state that does not match the issued one.
	 *
	 * @return void
	 */
	public function test_callback_rejects_mismatched_state() {
		$this->issue_state();

		$response = rest_get_server()->dispatch( $this->request( 'wrong-state!' ) );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Tests the callback rejects a state whose transient has expired.
	 *
	 * @return void
	 */
	public function test_callback_rejects_expired_state() {
		$this->issue_state( -1 );

		$response = rest_get_server()->dispatch( $this->request( 'issued-state' ) );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Tests the callback accepts the issued state and consumes it.
	 *
	 * @return void
	 */
	public function test_callback_accepts_matching_state_and_consumes_it() {
		$this->issue_state();

		$this->assertTrue( $this->dispatch_until_redirect( $this->request( 'issued-state' ) ) );
		$this->assertFalse( get_transient( PINTEREST_FOR_WOOCOMMERCE_CONNECT_NONCE ) );
	}

	/**
	 * Tests the callback rejects a state that has already been used.
	 *
	 * @return void
	 */
	public function test_callback_rejects_replayed_state() {
		$this->issue_state();
		$request = $this->request( 'issued-state' );

		$this->assertTrue( $this->dispatch_until_redirect( $request ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Builds a callback request, optionally carrying a state parameter.
	 *
	 * @param string|null $state The state parameter, or null to omit it.
	 * @return WP_REST_Request
	 */
	private function request( ?string $state = null ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', self::ROUTE );

		if ( null !== $state ) {
			$request->set_param( 'state', $state );
		}

		return $request;
	}

	/**
	 * Stores an issued state the way the connect flow does.
	 *
	 * @param int $expiration Transient lifetime in seconds.
	 * @return void
	 */
	private function issue_state( int $expiration = MINUTE_IN_SECONDS ): void {
		set_transient( PINTEREST_FOR_WOOCOMMERCE_CONNECT_NONCE, 'issued-state', $expiration );
	}

	/**
	 * Dispatches the request and reports whether the handler reached its redirect.
	 *
	 * The callback always ends in a redirect followed by exit, so the redirect is turned into an
	 * exception to observe that the handler ran without terminating the process.
	 *
	 * @param WP_REST_Request $request The request to dispatch.
	 * @return bool True when the handler redirected, false when the request was rejected.
	 */
	private function dispatch_until_redirect( WP_REST_Request $request ): bool {
		$redirect = function () {
			throw new RuntimeException( 'redirected' );
		};

		add_filter( 'wp_redirect', $redirect );

		try {
			rest_get_server()->dispatch( $request );
			return false;
		} catch ( RuntimeException $e ) {
			return true;
		} finally {
			remove_filter( 'wp_redirect', $redirect );
		}
	}
}
