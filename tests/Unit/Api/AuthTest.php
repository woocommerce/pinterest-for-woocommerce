<?php
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- OAuth fixtures.
/**
 * Class AuthTest
 *
 * Covers the OAuth callback state validation lifecycle.
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Api;

use RuntimeException;
use Pinterest_For_Woocommerce;
use WP_REST_Request;
use WP_Test_REST_TestCase;

/**
 * Tests for the OAuth callback endpoint.
 *
 * @version x.x.x
 */
class AuthTest extends WP_Test_REST_TestCase {

	private const ROUTE = '/pinterest/v1/oauth/callback';
	/** @var string Native redirect location. */
	private $last_redirect = '';
	/** @var mixed Request-local API token cache before this test. */
	private $previous_api_token;

	/**
	 * Clears any issued state before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		delete_transient( PINTEREST_FOR_WOOCOMMERCE_CONNECT_NONCE );
		$token = new \ReflectionProperty( \Automattic\WooCommerce\Pinterest\API\Base::class, 'token' );
		$token->setAccessible( true );
		$this->previous_api_token = $token->getValue();
		$token->setValue( null, null );
	}

	/**
	 * Clears issued state and redirect hooks after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$token = new \ReflectionProperty( \Automattic\WooCommerce\Pinterest\API\Base::class, 'token' );
		$token->setAccessible( true );
		$token->setValue( null, $this->previous_api_token );
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
	 * Tests a rejected request is sent back to the settings page rather than served as a raw error.
	 *
	 * @return void
	 */
	public function test_callback_rejection_redirects_to_settings_page() {
		rest_get_server()->dispatch( $this->request( 'not-issued' ) );

		$this->assertSame( 10, has_filter( 'rest_pre_serve_request', array( $this->auth(), 'redirect_to_settings_page' ) ) );
	}

	/**
	 * Tests an accepted request is served normally with no redirect hooked.
	 *
	 * @return void
	 */
	public function test_callback_acceptance_does_not_hook_redirect() {
		$this->issue_state();

		$this->assertTrue( $this->dispatch_until_redirect( $this->request( 'issued-state' ) ) );
		$this->assertFalse( has_filter( 'rest_pre_serve_request', array( $this->auth(), 'redirect_to_settings_page' ) ) );
	}

	/**
	 * Malformed callback data must not replace a previously working connection.
	 *
	 * @dataProvider malformed_callback_data
	 * @param mixed $token Encoded token or a non-string request value.
	 * @param mixed $info Encoded connection information.
	 * @return void
	 */
	public function test_malformed_callback_retains_existing_identity_and_settings( $token, $info ) {
		$this->issue_state();
		Pinterest_For_Woocommerce::save_token_data( array( 'access_token' => 'previous-token' ) );
		Pinterest_For_Woocommerce::save_connection_info_data(
			array(
				'merchant_id'   => 'previous-merchant',
				'advertiser_id' => 'previous-advertiser',
			)
		);
		Pinterest_For_Woocommerce::save_setting( 'tracking_advertiser', 'previous-advertiser' );
		Pinterest_For_Woocommerce::save_setting( 'enable_debug_logging', true );
		$before       = Pinterest_For_Woocommerce::get_data( 'token_data', true );
		$before_info  = Pinterest_For_Woocommerce::get_data( 'connection_info_data', true );
		$before_hooks = did_action( 'pinterest_for_woocommerce_token_saved' );
		$request      = $this->request( 'issued-state' );
		$request->set_param( 'token_data', $token );
		$request->set_param( 'info', $info );
		$requests  = array();
		$transport = function ( $response, $args, $url ) use ( &$requests ) {
			$requests[] = $url;
			return new \WP_Error( 'unexpected-request', 'No API request should be made.' );
		};
		add_filter( 'pre_http_request', $transport, 10, 3 );
		try {
			$this->assertTrue( $this->dispatch_until_redirect( $request ) );
		} finally {
			remove_filter( 'pre_http_request', $transport, 10 );
		}
		$this->assertSame( array(), $requests );
		parse_str( wp_parse_url( $this->last_redirect, PHP_URL_QUERY ), $redirect_args );
		$this->assertSame( 'Invalid connection data, please try again later.', $redirect_args['error'] );
		$this->assertSame( $before, Pinterest_For_Woocommerce::get_data( 'token_data', true ) );
		$this->assertSame( $before_info, Pinterest_For_Woocommerce::get_data( 'connection_info_data', true ) );
		$this->assertSame( 'previous-advertiser', Pinterest_For_Woocommerce::get_setting( 'tracking_advertiser' ) );
		$this->assertTrue( Pinterest_For_Woocommerce::get_setting( 'enable_debug_logging' ) );
		$this->assertSame( $before_hooks, did_action( 'pinterest_for_woocommerce_token_saved' ) );
		$this->assertFalse( get_transient( PINTEREST_FOR_WOOCOMMERCE_CONNECT_NONCE ) );
	}

	/** @return array Encoded malformed inputs supplied to the native route. */
	public function malformed_callback_data() {
		$valid_token = base64_encode( '{"access_token":"new-token"}' );
		$valid_info  = base64_encode( '{"advertiser_id":"new-advertiser","feature_flags":{}}' );
		return array(
			'invalid token base64'     => array( '***', $valid_info ),
			'scalar token'             => array( base64_encode( '42' ), $valid_info ),
			'invalid token JSON'       => array( base64_encode( '{bad' ), $valid_info ),
			'non-string token'         => array( array( 'access_token' => 'new-token' ), $valid_info ),
			'missing token value'      => array( base64_encode( '{}' ), $valid_info ),
			'non-string refresh token' => array(
				base64_encode( '{"access_token":"new-token","refresh_token":[]}' ),
				$valid_info,
			),
			'invalid info JSON'        => array( $valid_token, base64_encode( '{bad' ) ),
			'scalar info'              => array( $valid_token, base64_encode( '42' ) ),
			'missing advertiser'       => array( $valid_token, base64_encode( '{"feature_flags":{}}' ) ),
			'non-scalar advertiser'    => array(
				$valid_token,
				base64_encode( '{"advertiser_id":[],"feature_flags":{}}' ),
			),
			'empty advertiser'         => array( $valid_token, base64_encode( '{"advertiser_id":""}' ) ),
			'zero string advertiser'   => array( $valid_token, base64_encode( '{"advertiser_id":"0"}' ) ),
			'zero integer advertiser'  => array( $valid_token, base64_encode( '{"advertiser_id":0}' ) ),
			'non-string info'          => array( $valid_token, array( 'advertiser_id' => 'new-advertiser' ) ),
			'invalid info base64'      => array( $valid_token, '***' ),
			'empty access token'       => array( base64_encode( '{"access_token":""}' ), $valid_info ),
			'non-string access token'  => array( base64_encode( '{"access_token":[]}' ), $valid_info ),
		);
	}

	/**
	 * @dataProvider valid_advertisers
	 * @param string|int $advertiser Native callback advertiser ID.
	 * @param bool       $numeric_json Whether to supply an integer beyond PHP's native range.
	 * @return void
	 */
	public function test_valid_callback_persists_connection_and_runs_native_commerce_pipeline(
		$advertiser,
		$numeric_json = false
	) {
		$this->issue_state();
		Pinterest_For_Woocommerce::save_setting( 'enable_debug_logging', true );
		$requests  = array();
		$transport = function ( $response, $args, $url ) use ( &$requests ) {
			$path       = wp_parse_url( $url, PHP_URL_PATH );
			$requests[] = array(
				'request'       => $args['method'] . ' ' . $path,
				'authorization' => $args['headers']['Authorization'],
			);
			$bodies     = array(
				'/v5/integrations/commerce' => array(
					'id'                   => 'integration-88',
					'external_business_id' => 'business-42',
					'partner_metadata'     => 'saved-metadata',
				),
				'/v5/user_account'          => array(
					'id'           => 'user-3',
					'username'     => 'merchant-name',
					'account_type' => 'BUSINESS',
				),
				'/v5/user_account/websites' => array(
					'items' => array(
						array(
							'status'  => 'verified',
							'website' => 'shop.example',
						),
					),
				),
			);
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( $bodies[ $path ] ?? array() ),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
			);
		};
		$identity  = function () {
			return 'business-42';
		};
		add_filter( 'pre_http_request', $transport, 10, 3 );
		add_filter( 'wc_pinterest_external_business_id', $identity );
		$request = $this->request( 'issued-state' );
		$request->set_param(
			'token_data',
			base64_encode(
				wp_json_encode(
					array(
						'access_token'  => 'new-token',
						'refresh_token' => 'new-refresh',
						'expires_in'    => 3600,
						'scopes'        => 'read,write',
					)
				)
			)
		);
		$request->set_param(
			'info',
			base64_encode(
				wp_json_encode(
					array(
						'merchant_id'   => 'merchant-42',
						'advertiser_id' => $advertiser,
						'tag_id'        => 'tag-9',
						'shop_metadata' => array( 'region' => 'eu' ),
						'feature_flags' => array(
							'tags'    => true,
							'CAPI'    => false,
							'catalog' => true,
						),
					)
				)
			)
		);
		if ( $numeric_json ) {
			$request->set_param(
				'info',
				base64_encode(
					'{"merchant_id":"merchant-42","advertiser_id":12345678901234567890,'
					. '"tag_id":"tag-9","shop_metadata":{"region":"eu"},'
					. '"feature_flags":{"tags":true,"CAPI":false,"catalog":true}}'
				)
			);
		}
		try {
			$this->assertTrue( $this->dispatch_until_redirect( $request ) );
		} finally {
			remove_filter( 'pre_http_request', $transport, 10 );
			remove_filter( 'wc_pinterest_external_business_id', $identity );
		}
		$this->assertSame(
			array(
				array(
					'request'       => 'POST /v5/integrations/commerce',
					'authorization' => 'Bearer new-token',
				),
				array(
					'request'       => 'GET /v5/user_account',
					'authorization' => 'Bearer new-token',
				),
				array(
					'request'       => 'GET /v5/user_account/websites',
					'authorization' => 'Bearer new-token',
				),
			),
			$requests
		);
		$this->assertSame( array( 'access_token' => 'new-token' ), Pinterest_For_Woocommerce::get_access_token() );
		$stored_token = Pinterest_For_Woocommerce::get_data( 'token_data', true );
		$this->assertSame(
			'new-refresh',
			\Automattic\WooCommerce\Pinterest\Crypto::decrypt( $stored_token['refresh_token'] )
		);
		$this->assertSame( 3600, $stored_token['expires_in'] );
		$this->assertSame( 'read,write', $stored_token['scopes'] );
		$this->assertEquals(
			array(
				'merchant_id'   => 'merchant-42',
				'advertiser_id' => $advertiser,
				'tag_id'        => 'tag-9',
				'shop_metadata' => (object) array( 'region' => 'eu' ),
			),
			Pinterest_For_Woocommerce::get_data( 'connection_info_data', true )
		);
		$this->assertSame(
			array(
				'id'                   => 'integration-88',
				'external_business_id' => 'business-42',
				'partner_metadata'     => 'saved-metadata',
			),
			Pinterest_For_Woocommerce::get_data( 'integration_data', true )
		);
		$this->assertSame( $advertiser, Pinterest_For_Woocommerce::get_setting( 'tracking_advertiser' ) );
		$this->assertSame( 'tag-9', Pinterest_For_Woocommerce::get_setting( 'tracking_tag' ) );
		$this->assertTrue( Pinterest_For_Woocommerce::get_setting( 'track_conversions' ) );
		$this->assertFalse( Pinterest_For_Woocommerce::get_setting( 'track_conversions_capi' ) );
		$this->assertTrue( Pinterest_For_Woocommerce::get_setting( 'product_sync_enabled' ) );
		$this->assertTrue( Pinterest_For_Woocommerce::get_setting( 'enable_debug_logging' ) );
		$this->assertSame(
			array( 'shop.example' ),
			Pinterest_For_Woocommerce::get_setting( 'account_data' )['verified_user_websites']
		);
		parse_str( wp_parse_url( $this->last_redirect, PHP_URL_QUERY ), $redirect_args );
		$this->assertSame( 'claim-website', $redirect_args['step'] );
		$this->assertArrayNotHasKey( 'error', $redirect_args );
		$this->assertFalse( get_transient( PINTEREST_FOR_WOOCOMMERCE_CONNECT_NONCE ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );
		$this->assertCount( 3, $requests );
	}

	/** @return array Both previously accepted advertiser ID shapes. */
	public function valid_advertisers() {
		return array(
			'string'             => array( 'advertiser-7' ),
			'integer'            => array( 7 ),
			'large JSON integer' => array( '12345678901234567890', true ),
		);
	}

	/**
	 * Returns the Auth controller instance registered for the callback route.
	 *
	 * @return object
	 */
	private function auth() {
		return rest_get_server()->get_routes()[ self::ROUTE ][0]['permission_callback'][0];
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
		$redirect = function ( $location ) {
			$this->last_redirect = $location;
			throw new RuntimeException( 'redirected' );
		};

		add_filter( 'wp_redirect', $redirect );

		try {
			rest_get_server()->dispatch( $request );
			return false;
		} catch ( RuntimeException $e ) {
			$this->assertSame( 'redirected', $e->getMessage() );
			return true;
		} finally {
			remove_filter( 'wp_redirect', $redirect );
		}
	}
}
