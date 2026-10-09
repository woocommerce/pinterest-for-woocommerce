<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\API\APIV5;
use Automattic\WooCommerce\Pinterest\Notes\TokenInvalidFailure;
use Automattic\WooCommerce\Pinterest\PinterestApiException;
use Automattic\WooCommerce\Pinterest\ProductFeedStatus;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

/**
 * Tests disconnecting from Pinterest when the stored token is rejected.
 *
 * Every 401 fires the auth-failure action that resets the connection. The reset used to run
 * the full disconnect, whose remote cleanup requests got the same 401 and re-entered it
 * without bound until PHP's execution time ran out, which made wp-admin unreachable.
 *
 * @version x.x.x
 */
class PinterestForWoocommerceDisconnectTest extends WP_UnitTestCase {

	/**
	 * Pinterest API requests the fake API received, as "METHOD path" strings.
	 *
	 * @var string[]
	 */
	private static $requests = array();

	/**
	 * Sets up a business account whose token Pinterest rejects.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		self::$requests = array();

		Pinterest_For_Woocommerce::set_default_settings();
		Pinterest_For_Woocommerce::save_token_data( array( 'access_token' => 'rejected-access-token' ) );
		Pinterest_For_Woocommerce::save_setting( 'tracking_advertiser', '114141241212' );
		Pinterest_For_Woocommerce::save_setting(
			'account_data',
			array(
				'id'         => '123456789',
				'is_partner' => true,
			)
		);
		Pinterest_For_Woocommerce::save_data( 'integration_data', array( 'external_business_id' => 'ebi-123' ) );
		TokenInvalidFailure::delete_failure_note();

		add_filter( 'pre_http_request', array( self::class, 'reject_every_request' ), 10, 3 );
	}

	/**
	 * Tears down the test case.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		// Only this test's own filter: parent::tearDown() restores the hooks that existed before it.
		remove_filter( 'pre_http_request', array( self::class, 'reject_every_request' ), 10 );

		parent::tearDown();

		TokenInvalidFailure::delete_failure_note();
	}

	/**
	 * Any API call that gets a 401 resets the connection without calling the API again.
	 *
	 * @return void
	 */
	public function test_a_401_resets_the_connection_without_further_requests() {
		$this->assertTrue( Pinterest_For_Woocommerce::is_business_connected() );

		try {
			APIV5::get_advertisers();
			$this->fail( 'The rejected token should surface as a 401 exception.' );
		} catch ( PinterestApiException $e ) {
			$this->assertSame( 401, $e->getCode() );
		}

		$this->assertSame( array( 'GET ad_accounts' ), self::$requests, 'The reset must not call the API.' );
		$this->assert_connection_cleared();
	}

	/**
	 * A disconnect started while the token is rejected finishes with a bounded number of
	 * requests and still clears the local connection.
	 *
	 * @return void
	 */
	public function test_disconnect_with_a_rejected_token_is_bounded_and_clears_the_connection() {
		$this->assertTrue( Pinterest_For_Woocommerce::disconnect() );

		$this->assertSame(
			array( 'GET catalogs/feeds' ),
			self::$requests,
			'Only the feed listing runs: the nested reset clears the integration data before the commerce integration delete.'
		);
		$this->assert_connection_cleared( false );
	}

	/**
	 * A disconnect started while another one is running (for example by a hook that reacts to
	 * one of its cleanup requests) is refused, so the two cannot feed each other.
	 *
	 * @return void
	 */
	public function test_disconnect_refuses_a_nested_disconnect() {
		$nested_results = array();
		add_filter(
			'pre_http_request',
			function ( $response, $args, $url ) use ( &$nested_results ) {
				if ( false !== strpos( $url, 'catalogs/feeds' ) ) {
					$nested_results[] = Pinterest_For_Woocommerce::disconnect();
				}
				return $response;
			},
			9,
			3
		);

		$this->assertTrue( Pinterest_For_Woocommerce::disconnect() );

		$this->assertSame( array( false ), $nested_results, 'The nested disconnect must return false without doing anything.' );
		$this->assertSame( array( 'GET catalogs/feeds' ), self::$requests, 'The nested disconnect must not make requests of its own.' );
		$this->assert_connection_cleared( false );
	}

	/**
	 * A disconnect with a valid token deletes the plugin's feed and the commerce integration at
	 * Pinterest, then clears the local connection.
	 *
	 * @return void
	 */
	public function test_disconnect_with_a_valid_token_deletes_the_remote_feed_and_commerce_integration() {
		remove_filter( 'pre_http_request', array( self::class, 'reject_every_request' ), 10 );
		add_filter( 'pre_http_request', array( self::class, 'accept_every_request' ), 10, 3 );

		$this->assertTrue( Pinterest_For_Woocommerce::disconnect() );

		$this->assertSame(
			array( 'GET catalogs/feeds', 'DELETE catalogs/feeds/feed-123', 'DELETE integrations/commerce/ebi-123' ),
			self::$requests
		);
		$this->assert_connection_cleared( false );
		$this->assertFalse( TokenInvalidFailure::note_exists() );
	}

	/**
	 * An unexpected error during a user-initiated disconnect keeps the local connection data so
	 * the merchant can retry, while the remaining remote cleanup still runs.
	 *
	 * @dataProvider provide_cleanup_throwables
	 *
	 * @param string $throwable_class The class of the throwable the feed listing raises.
	 * @return void
	 */
	public function test_disconnect_keeps_local_data_when_a_remote_cleanup_throws( string $throwable_class ) {
		remove_filter( 'pre_http_request', array( self::class, 'reject_every_request' ), 10 );
		$this->throw_on_feed_listing( $throwable_class );

		$this->assertFalse( Pinterest_For_Woocommerce::disconnect() );

		$this->assertSame( array( 'DELETE integrations/commerce/ebi-123' ), self::$requests, 'The commerce integration deletion still runs.' );
		$this->assertTrue( Pinterest_For_Woocommerce::is_connected() );
		$this->assertFalse( TokenInvalidFailure::note_exists() );
	}

	/**
	 * Throwables a remote cleanup may raise that are not Pinterest API errors.
	 *
	 * @return array
	 */
	public function provide_cleanup_throwables(): array {
		return array(
			'exception' => array( \RuntimeException::class ),
			'error'     => array( \Error::class ),
		);
	}

	/**
	 * A failed cleanup does not leave the disconnect locked, so the merchant can retry it.
	 *
	 * @return void
	 */
	public function test_disconnect_can_run_again_after_a_failed_cleanup() {
		remove_filter( 'pre_http_request', array( self::class, 'reject_every_request' ), 10 );
		$throwing_filter = $this->throw_on_feed_listing( \RuntimeException::class );

		$this->assertFalse( Pinterest_For_Woocommerce::disconnect() );

		remove_filter( 'pre_http_request', $throwing_filter, 10 );
		add_filter( 'pre_http_request', array( self::class, 'accept_every_request' ), 10, 3 );
		self::$requests = array();

		$this->assertTrue( Pinterest_For_Woocommerce::disconnect() );
		$this->assert_connection_cleared( false );
	}

	/**
	 * A disconnect with no business connected skips the remote cleanup and writes no account
	 * data back.
	 *
	 * @return void
	 */
	public function test_disconnect_without_a_business_connected_leaves_no_account_data() {
		Pinterest_For_Woocommerce::save_token_data( array( 'access_token' => 'valid-access-token' ) );
		Pinterest_For_Woocommerce::save_setting( 'account_data', array( 'id' => '123456789' ) );

		$this->assertTrue( Pinterest_For_Woocommerce::disconnect() );

		$this->assertSame( array(), self::$requests );
		$this->assertFalse( Pinterest_For_Woocommerce::is_connected() );
		$this->assertFalse( Pinterest_For_Woocommerce::get_setting( 'account_data', true ) );
	}

	/**
	 * A 401 raised by a deliberate disconnect's own cleanup clears the connection without
	 * asking the merchant to reconnect.
	 *
	 * @return void
	 */
	public function test_disconnect_with_a_rejected_token_adds_no_token_invalid_note() {
		$this->assertTrue( Pinterest_For_Woocommerce::disconnect() );

		$this->assert_connection_cleared( false );
		$this->assertFalse( TokenInvalidFailure::note_exists() );
	}

	/**
	 * Resetting the connection on its own never calls the API.
	 *
	 * @return void
	 */
	public function test_reset_connection_makes_no_requests() {
		Pinterest_For_Woocommerce::reset_connection();

		$this->assertSame( array(), self::$requests );
		$this->assert_connection_cleared();
	}

	/**
	 * Resetting the connection clears the feed generation telemetry held for the request and
	 * unschedules the commerce integration jobs, which would otherwise run against the old
	 * connection.
	 *
	 * @return void
	 */
	public function test_reset_connection_clears_runtime_feed_telemetry_and_retry_jobs() {
		ProductFeedStatus::set(
			array(
				'status'        => 'generated',
				'product_count' => 10,
				'error_message' => 'Stale error.',
			)
		);
		as_schedule_single_action( time() + HOUR_IN_SECONDS, 'pinterest-for-woocommerce-create-commerce-integration-retry', array( 'attempt' => 1 ), 'pinterest-for-woocommerce' );
		as_schedule_single_action( time() + HOUR_IN_SECONDS, 'pinterest-for-woocommerce-sync-commerce-integration', array(), 'pinterest-for-woocommerce' );
		$this->assertSame( 'generated', ProductFeedStatus::get()['status'] );
		$this->assertTrue( as_has_scheduled_action( 'pinterest-for-woocommerce-create-commerce-integration-retry' ) );
		$this->assertTrue( as_has_scheduled_action( 'pinterest-for-woocommerce-sync-commerce-integration' ) );

		Pinterest_For_Woocommerce::reset_connection();

		$this->assertEquals( ProductFeedStatus::STATE_PROPS, ProductFeedStatus::get() );
		$this->assertFalse( as_has_scheduled_action( 'pinterest-for-woocommerce-create-commerce-integration-retry' ) );
		$this->assertFalse( as_has_scheduled_action( 'pinterest-for-woocommerce-sync-commerce-integration' ) );
	}

	/**
	 * Data the reset deleted must not be served from the runtime settings cache for the rest of
	 * the request, otherwise the disconnect that follows still finds an integration to delete.
	 *
	 * @return void
	 */
	public function test_reset_connection_invalidates_the_runtime_data_cache() {
		// Prime the cache the way a fresh request does: a forced read, then no write marking it dirty.
		Pinterest_For_Woocommerce::get_data( 'integration_data', true );
		$dirty_settings = new \ReflectionProperty( Pinterest_For_Woocommerce::class, 'dirty_settings' );
		$dirty_settings->setAccessible( true );
		$dirty_settings->setValue( null, array() );
		$this->assertSame( 'ebi-123', Pinterest_For_Woocommerce::get_data( 'integration_data' )['external_business_id'] );

		Pinterest_For_Woocommerce::reset_connection();

		$this->assertNull( Pinterest_For_Woocommerce::get_data( 'integration_data' ) );
		$this->assertSame( array(), self::$requests );
	}

	/**
	 * Asserts that no connection data is left.
	 *
	 * After a reset, the merchant was told the token is invalid and only the ad credits currency
	 * info remains. A disconnect flushes the settings after any nested reset, so nothing remains.
	 *
	 * @param bool $after_reset Whether the connection was cleared by reset_connection() alone.
	 * @return void
	 */
	private function assert_connection_cleared( bool $after_reset = true ) {
		$this->assertFalse( Pinterest_For_Woocommerce::is_connected() );
		$this->assertFalse( get_option( PINTEREST_FOR_WOOCOMMERCE_DATA_NAME ) );
		$this->assertFalse( Pinterest_For_Woocommerce::get_setting( 'tracking_advertiser', true ) );
		$this->assertFalse( Pinterest_For_Woocommerce::is_business_connected() );

		if ( ! $after_reset ) {
			$this->assertFalse( Pinterest_For_Woocommerce::get_setting( 'account_data', true ) );
			return;
		}

		// Only the ad credits currency info remains, which the landing page needs on the next render.
		$this->assertSame(
			array( 'currency_credit_info' ),
			array_keys( Pinterest_For_Woocommerce::get_setting( 'account_data', true ) )
		);
		$this->assertTrue( TokenInvalidFailure::note_exists() );
	}

	/**
	 * Makes the feed listing throw the given throwable, while every other request succeeds.
	 *
	 * @param string $throwable_class The class of the throwable to raise.
	 * @return callable The added filter, so a test can remove it.
	 */
	private function throw_on_feed_listing( string $throwable_class ): callable {
		$filter = function ( $response, $args, $url ) use ( $throwable_class ) {
			if ( false === strpos( $url, 'api.pinterest.com' ) ) {
				return $response;
			}

			if ( false !== strpos( $url, 'catalogs/feeds' ) ) {
				throw new $throwable_class( 'Unexpected failure while listing feeds.' );
			}

			self::$requests[] = $args['method'] . ' ' . preg_replace( '#^/v5/#', '', (string) wp_parse_url( $url, PHP_URL_PATH ) );

			return self::json_response( 204, array() );
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );

		return $filter;
	}

	/**
	 * Fakes a Pinterest API that accepts the token, recording each request. The feed listing
	 * returns one feed generated by this plugin.
	 *
	 * @param false|array $response Preempted response.
	 * @param array       $args     Request arguments.
	 * @param string      $url      Request URL.
	 * @return false|array
	 */
	public static function accept_every_request( $response, $args, $url ) {
		if ( false === strpos( $url, 'api.pinterest.com' ) ) {
			return $response;
		}

		$request          = $args['method'] . ' ' . preg_replace( '#^/v5/#', '', (string) wp_parse_url( $url, PHP_URL_PATH ) );
		self::$requests[] = $request;

		if ( 'GET catalogs/feeds' !== $request ) {
			return self::json_response( 204, array() );
		}

		return self::json_response(
			200,
			array(
				'items' => array(
					array(
						'id'       => 'feed-123',
						'location' => trailingslashit( wp_get_upload_dir()['baseurl'] ) . PINTEREST_FOR_WOOCOMMERCE_LOG_PREFIX . '-feed.xml',
					),
				),
			)
		);
	}

	/**
	 * Builds a faked JSON HTTP response.
	 *
	 * @param int   $code HTTP status code.
	 * @param array $body Response body.
	 * @return array
	 */
	private static function json_response( int $code, array $body ): array {
		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => wp_json_encode( $body ),
			'response' => array(
				'code'    => $code,
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => '',
		);
	}

	/**
	 * Fakes a Pinterest API that rejects the token on every request, recording each one.
	 *
	 * @param false|array $response Preempted response.
	 * @param array       $args     Request arguments.
	 * @param string      $url      Request URL.
	 * @return false|array
	 */
	public static function reject_every_request( $response, $args, $url ) {
		if ( false === strpos( $url, 'api.pinterest.com' ) ) {
			return $response;
		}

		self::$requests[] = $args['method'] . ' ' . preg_replace( '#^/v5/#', '', (string) wp_parse_url( $url, PHP_URL_PATH ) );

		/*
		 * A non-401 does not fire the disconnect action, so a reintroduced recursion stops here and
		 * the request-list assertion fails with a readable diff instead of the CI job timeout.
		 */
		if ( count( self::$requests ) > 5 ) {
			return self::json_response( 500, array() );
		}

		return self::json_response(
			401,
			array(
				'code'    => 2,
				'message' => 'Authentication failed.',
			)
		);
	}
}
