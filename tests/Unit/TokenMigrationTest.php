<?php
/**
 * Tests the V3 to V5 credential migration that runs when upgrading from a pre-1.4.0 version.
 *
 * @package Automattic\WooCommerce\Pinterest\Tests
 */

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use ActionScheduler;
use ActionScheduler_Store;
use Automattic\WooCommerce\Pinterest\API\Base;
use Automattic\WooCommerce\Pinterest\Crypto;
use Automattic\WooCommerce\Pinterest\Notes\TokenExchangeFailure;
use Automattic\WooCommerce\Pinterest\PluginUpdate;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

/**
 * Upgrades a store connected with a V3 token and follows the token exchange and its retries.
 *
 * Every case goes through the plugin's own update entry point and the real Action Scheduler
 * queue. Only the Pinterest API is replaced, at the HTTP layer.
 *
 * @version x.x.x
 */
class TokenMigrationTest extends WP_UnitTestCase {

	private const EXCHANGE_URL = 'https://api.pinterest.com/v3/oauth/commerce_integrations/token/exchange/';

	private const V3_TOKEN = 'v3-access-token';

	/**
	 * Responses the fake API gives to the token exchange requests, in order.
	 *
	 * @var array[]
	 */
	private $exchange_responses = array();

	/**
	 * Requests the fake API received.
	 *
	 * @var array[] Each with the keys method, url and authorization.
	 */
	private $requests = array();

	/**
	 * V5 token another test left in the API classes' cache.
	 *
	 * @var mixed
	 */
	private $previous_api_token;

	/**
	 * Seeds a store that was connected on a pre-1.4.0 version.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->previous_api_token = $this->api_token_cache()->getValue();
		$this->api_token_cache()->setValue( null, null );

		update_option( PluginUpdate::PLUGIN_UPDATE_VERSION_OPTION, '1.3.9' );
		delete_option( PINTEREST_FOR_WOOCOMMERCE_PINTEREST_API_VERSION );

		Pinterest_For_Woocommerce::save_settings(
			array(
				'tracking_advertiser' => '549765662491',
				'tracking_tag'        => '2613286171854',
			)
		);
		Pinterest_For_Woocommerce::save_settings(
			array(
				'token'       => array( 'access_token' => Crypto::encrypt( self::V3_TOKEN ) ),
				'merchant_id' => '1479839719476',
			),
			PINTEREST_FOR_WOOCOMMERCE_DATA_NAME
		);

		add_filter( 'pre_http_request', array( $this, 'fake_pinterest_api' ), 10, 3 );
	}

	/**
	 * Restores what the rolled back transaction does not: the plugin's in-memory caches.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();

		$this->api_token_cache()->setValue( null, $this->previous_api_token );
		Pinterest_For_Woocommerce::get_settings( true );
		Pinterest_For_Woocommerce::get_settings( true, PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );
	}

	/**
	 * The V5 token the API classes cache for the lifetime of the process.
	 *
	 * @return \ReflectionProperty
	 */
	private function api_token_cache(): \ReflectionProperty {
		$token = new \ReflectionProperty( Base::class, 'token' );
		$token->setAccessible( true );

		return $token;
	}

	/**
	 * Stands in for the Pinterest API.
	 *
	 * @param mixed  $response Short-circuit value.
	 * @param array  $args     Request arguments.
	 * @param string $url      Request URL.
	 * @return array
	 */
	public function fake_pinterest_api( $response, $args, $url ) {
		$this->requests[] = array(
			'method'        => $args['method'],
			'url'           => $url,
			'authorization' => $args['headers']['Authorization'] ?? '',
		);

		if ( false !== strpos( $url, 'token/exchange' ) ) {
			return array_shift( $this->exchange_responses ) ?? new \WP_Error( 'unexpected_exchange', 'No response was queued for this exchange request.' );
		}

		// Everything the plugin asks the V5 API once it holds a V5 token.
		return self::response( 200, array() );
	}

	/**
	 * The exchange is requested from the V3 API with the V3 token, and its result becomes the V5 connection.
	 *
	 * @return void
	 */
	public function test_upgrade_exchanges_the_v3_token_for_v5_credentials() {
		$this->exchange_responses = array( self::successful_exchange() );

		$this->load_plugin_on_a_new_request();

		$this->assertSame(
			array(
				'method'        => 'POST',
				'url'           => self::EXCHANGE_URL,
				'authorization' => 'Bearer ' . self::V3_TOKEN,
			),
			$this->requests[0]
		);
		$this->assertCount( 1, $this->exchange_requests() );

		$this->assertSame( 'v5-access-token', Crypto::decrypt( Pinterest_For_Woocommerce::get_data( 'token_data', true )['access_token'] ) );

		// Setting up the V5 connection right after the exchange already uses the new token.
		$v5_requests = array_slice( $this->requests, 1 );
		$this->assertNotEmpty( $v5_requests );
		$this->assertSame( array( 'Bearer v5-access-token' ), array_values( array_unique( array_column( $v5_requests, 'authorization' ) ) ) );

		// The IDs the V3 connection used are carried over to the V5 connection.
		$this->assertSame(
			array(
				'advertiser_id' => '549765662491',
				'tag_id'        => '2613286171854',
				'merchant_id'   => '1479839719476',
			),
			Pinterest_For_Woocommerce::get_data( 'connection_info_data', true )
		);

		$this->assertSame( 'v5', Pinterest_For_Woocommerce::get_api_version() );
		$this->assertSame( array(), $this->pending_retries() );
		$this->assertFalse( TokenExchangeFailure::note_exists() );
	}

	/**
	 * Once migrated, neither later page loads nor a stale retry job exchange the token again.
	 *
	 * @return void
	 */
	public function test_migrated_store_does_not_exchange_the_token_again() {
		$this->exchange_responses = array( self::successful_exchange() );
		$this->load_plugin_on_a_new_request();

		$this->load_plugin_on_a_new_request();
		// A retry job left in the queue from before the migration succeeded.
		$this->run_due_retry(
			as_schedule_single_action( time(), PluginUpdate::TOKEN_UPDATE_RETRY_HOOK, array( 'retry_count' => 2 ), PINTEREST_FOR_WOOCOMMERCE_PREFIX )
		);

		$this->assertCount( 1, $this->exchange_requests() );
		$this->assertSame( 'v5', Pinterest_For_Woocommerce::get_api_version() );
	}

	/**
	 * A store that was never connected has nothing to migrate.
	 *
	 * @return void
	 */
	public function test_upgrade_of_a_store_without_a_v3_token_requests_nothing() {
		Pinterest_For_Woocommerce::save_settings( array(), PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );

		$this->load_plugin_on_a_new_request();

		$this->assertSame( array(), $this->requests );
		$this->assertSame( '', Pinterest_For_Woocommerce::get_api_version() );
		$this->assertSame( array(), $this->pending_retries() );
		$this->assertFalse( TokenExchangeFailure::note_exists() );
	}

	/**
	 * A failed exchange keeps the V3 connection, tells the merchant and is retried once, later.
	 *
	 * @dataProvider provide_failed_exchanges
	 *
	 * @param array|\WP_Error $failure Response to the first exchange request.
	 * @return void
	 */
	public function test_failed_exchange_keeps_the_v3_connection_and_recovers_on_retry( $failure ) {
		$this->exchange_responses = array( $failure, self::successful_exchange() );

		$scheduled_after = time();
		$this->load_plugin_on_a_new_request();

		$this->assertSame( self::V3_TOKEN, Crypto::decrypt( Pinterest_For_Woocommerce::get_data( 'token', true )['access_token'] ) );
		$this->assertNull( Pinterest_For_Woocommerce::get_data( 'token_data', true ) );
		$this->assertSame( 'v3', Pinterest_For_Woocommerce::get_api_version() );
		$this->assertSame( '549765662491', Pinterest_For_Woocommerce::get_setting( 'tracking_advertiser', true ) );
		$this->assertTrue( TokenExchangeFailure::note_exists() );

		// More page loads before the retry is due neither exchange again nor queue another retry.
		$this->load_plugin_on_a_new_request();
		$this->load_plugin_on_a_new_request();
		$this->assertCount( 1, $this->exchange_requests() );

		$retries = $this->pending_retries();
		$this->assertCount( 1, $retries );
		$retry = reset( $retries );
		$this->assertSame( array( 'retry_count' => 2 ), $retry->get_args() );
		$this->assertEqualsWithDelta( $scheduled_after + 5 * MINUTE_IN_SECONDS, $retry->get_schedule()->get_date()->getTimestamp(), 5 );

		$this->run_due_retry( key( $retries ) );

		$this->assertCount( 2, $this->exchange_requests() );
		$this->assertSame( 'Bearer ' . self::V3_TOKEN, $this->exchange_requests()[1]['authorization'] );
		$this->assertSame( 'v5-access-token', Crypto::decrypt( Pinterest_For_Woocommerce::get_data( 'token_data', true )['access_token'] ) );
		$this->assertSame( 'v5', Pinterest_For_Woocommerce::get_api_version() );
		$this->assertSame( array(), $this->pending_retries() );
		$this->assertFalse( TokenExchangeFailure::note_exists() );
	}

	/**
	 * Failures that say nothing about the V3 token itself.
	 *
	 * @return array[]
	 */
	public function provide_failed_exchanges(): array {
		return array(
			'server error'          => array( self::response( 500, array( 'message' => 'Internal error' ) ) ),
			'connection error'      => array( new \WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ) ),
			'exchange not accepted' => array( self::response( 200, array( 'status' => 'failure' ) ) ),
			'unreadable body'       => array( self::response( 200, '<html>Bad gateway</html>' ) ),
		);
	}

	/**
	 * When every attempt fails, retries back off and stop after the third one.
	 *
	 * @return void
	 */
	public function test_exchange_that_keeps_failing_stops_after_three_retries() {
		$this->exchange_responses = array_fill( 0, 10, self::response( 500, array( 'message' => 'Internal error' ) ) );

		$this->load_plugin_on_a_new_request();

		$delays = array();
		// Bounded above the expected count, so a regression that never stops still ends the test.
		for ( $run = 0; $run < 6; $run++ ) {
			$retries = $this->pending_retries();
			if ( array() === $retries ) {
				break;
			}
			$this->assertCount( 1, $retries );

			$delays[] = (int) round( ( reset( $retries )->get_schedule()->get_date()->getTimestamp() - time() ) / MINUTE_IN_SECONDS );
			$this->run_due_retry( key( $retries ) );
		}

		$this->assertSame( array( 5, 10, 15 ), $delays );
		$this->assertCount( 4, $this->exchange_requests() );
		$this->assertSame( array(), $this->pending_retries() );

		// The merchant keeps the V3 connection data and the prompt to reconnect.
		$this->assertSame( self::V3_TOKEN, Crypto::decrypt( Pinterest_For_Woocommerce::get_data( 'token', true )['access_token'] ) );
		$this->assertNull( Pinterest_For_Woocommerce::get_data( 'token_data', true ) );
		$this->assertSame( 'v3', Pinterest_For_Woocommerce::get_api_version() );
		$this->assertTrue( TokenExchangeFailure::note_exists() );
	}

	/**
	 * Loads the plugin the way a new request does: one updater, listening for the retry job.
	 *
	 * The updater that the test bootstrap's own request registered is dropped first. Leaving it
	 * would run every retry job twice, which never happens on a site.
	 *
	 * @return void
	 */
	private function load_plugin_on_a_new_request(): void {
		remove_all_actions( PluginUpdate::TOKEN_UPDATE_RETRY_HOOK );
		Pinterest_For_Woocommerce()->maybe_update_plugin();
	}

	/**
	 * Runs a queued retry job in a request of its own.
	 *
	 * @param int $action_id Action Scheduler action ID.
	 * @return void
	 */
	private function run_due_retry( int $action_id ): void {
		$this->load_plugin_on_a_new_request();
		ActionScheduler::runner()->process_action( $action_id );
	}

	/**
	 * Retry jobs waiting in the queue.
	 *
	 * @return \ActionScheduler_Action[] Keyed by action ID.
	 */
	private function pending_retries(): array {
		return as_get_scheduled_actions(
			array(
				'hook'     => PluginUpdate::TOKEN_UPDATE_RETRY_HOOK,
				'status'   => ActionScheduler_Store::STATUS_PENDING,
				'group'    => PINTEREST_FOR_WOOCOMMERCE_PREFIX,
				'per_page' => -1,
			)
		);
	}

	/**
	 * Requests the fake API received for the token exchange.
	 *
	 * @return array[]
	 */
	private function exchange_requests(): array {
		return array_values(
			array_filter(
				$this->requests,
				function ( $request ) {
					return false !== strpos( $request['url'], 'token/exchange' );
				}
			)
		);
	}

	/**
	 * The response Pinterest gives to an accepted exchange.
	 *
	 * @return array
	 */
	private static function successful_exchange(): array {
		return self::response(
			200,
			array(
				'status' => 'success',
				'data'   => array(
					'access_token'             => 'v5-access-token',
					'refresh_token'            => 'v5-refresh-token',
					'token_type'               => 'bearer',
					'expires_in'               => 2592000,
					'refresh_token_expires_in' => 31536000,
					'scope'                    => 'ads:read ads:write catalogs:read catalogs:write',
				),
			)
		);
	}

	/**
	 * Builds an HTTP response in the shape the WordPress HTTP API returns.
	 *
	 * @param int          $code HTTP status code.
	 * @param array|string $body Body, JSON encoded when an array.
	 * @return array
	 */
	private static function response( int $code, $body ): array {
		return array(
			'headers'  => array(),
			'body'     => is_array( $body ) ? wp_json_encode( $body ) : $body,
			'response' => array(
				'code'    => $code,
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
