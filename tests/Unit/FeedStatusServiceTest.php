<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\FeedStatusService;
use Automattic\WooCommerce\Pinterest\LocalFeedConfigs;
use Automattic\WooCommerce\Pinterest\Logger;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

/**
 * Tests Feed Status Service helpers.
 */
class FeedStatusServiceTest extends WP_UnitTestCase {

	/**
	 * Mocked WC logger.
	 *
	 * @var object
	 */
	private $mock_logger;

	/**
	 * Captured feed update requests.
	 *
	 * @var array
	 */
	private $update_feed_requests = array();

	/**
	 * Whether to return an error response for feed update requests.
	 *
	 * @var bool
	 */
	private $should_fail_update_feed_request = false;

	/**
	 * Mock remote feed records returned by the Pinterest feeds API.
	 *
	 * @var array
	 */
	private $remote_feeds = array();

	/**
	 * Set up the WC logger mock and local feed configuration.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		LocalFeedConfigs::deregister();
		Pinterest_For_Woocommerce::set_default_settings();
		Pinterest_For_Woocommerce::save_setting( 'tracking_advertiser', '114141241212' );
		Pinterest_For_Woocommerce::save_setting( 'enable_debug_logging', false );
		Pinterest_For_Woocommerce::save_token_data( array( 'access_token' => 'some-fake-access-token' ) );
		Pinterest_For_Woocommerce::save_data(
			'local_feed_ids',
			array(
				Pinterest_For_Woocommerce::get_base_country() => 'feed-123',
			)
		);
		Pinterest_For_Woocommerce::remove_data( 'last_logged_processing_result_id' );
		Pinterest_For_Woocommerce::remove_data( 'last_retried_processing_result_id' );

		$this->mock_logger = new class() {

			/**
			 * Captured log entries.
			 *
			 * @var array
			 */
			public $entries = array();

			/**
			 * Capture a log entry.
			 *
			 * @param string $level   Log level.
			 * @param string $message Log message.
			 * @param array  $handler Log handler context.
			 * @return void
			 */
			public function log( $level, $message, $handler = array() ) {
				$this->entries[] = array(
					'level'   => $level,
					'message' => $message,
					'handler' => $handler,
				);
			}
		};

		Logger::$logger = $this->mock_logger;
		add_filter( 'pre_http_request', array( $this, 'intercept_api_request' ), 10, 3 );
	}

	/**
	 * Reset logger state.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		Logger::$logger = null;
		Pinterest_For_Woocommerce::remove_data( 'last_logged_processing_result_id' );
		Pinterest_For_Woocommerce::remove_data( 'last_retried_processing_result_id' );
		LocalFeedConfigs::deregister();
		remove_filter( 'pre_http_request', array( $this, 'intercept_api_request' ), 10 );

		parent::tearDown();
	}

	/**
	 * Tests that the full failed processing context is logged at error level.
	 *
	 * @return void
	 */
	public function test_log_failed_processing_result_logs_expected_payload_without_debug_logging_enabled() {
		$feed_url           = 'https://example.test/pinterest-for-woocommerce-feed-123.xml';
		$this->remote_feeds = array(
			array(
				'id'       => 'feed-123',
				'location' => $feed_url,
			),
		);
		$processing_results = $this->get_failed_processing_results();

		FeedStatusService::log_failed_processing_result( 'feed-123', $processing_results );

		$this->assertCount( 1, $this->mock_logger->entries );

		$entry            = $this->mock_logger->entries[0];
		$expected_message = implode(
			"\n",
			array(
				'Feed ingestion FAILED',
				'feed_id: feed-123',
				'processing_result_id: processing-result-1',
				'created_at: 2026-05-12T06:00:00',
				"feed_url: {$feed_url}",
				'validation_details: {"errors":{"FETCH_ERROR":1},"warnings":{"IMAGE_LINK_WARNING":2}}',
				'product_counts: {"original":10,"ingested":0}',
			)
		);

		$this->assertEquals( 'error', $entry['level'] );
		$this->assertEquals(
			array(
				'source' => PINTEREST_FOR_WOOCOMMERCE_LOG_PREFIX . '-feed-ingestion-failure',
			),
			$entry['handler']
		);
		$this->assertEquals(
			$expected_message,
			$entry['message']
		);
	}

	/**
	 * Tests that duplicate processing result IDs are not logged twice.
	 *
	 * @return void
	 */
	public function test_log_failed_processing_result_does_not_log_same_processing_result_id_twice() {
		$processing_results = $this->get_failed_processing_results();

		FeedStatusService::log_failed_processing_result( 'feed-123', $processing_results );
		FeedStatusService::log_failed_processing_result( 'feed-123', $processing_results );

		$this->assertCount( 1, $this->mock_logger->entries );
		$this->assertEquals(
			array(
				'feed-123' => 'processing-result-1',
			),
			Pinterest_For_Woocommerce::get_data( 'last_logged_processing_result_id' )
		);
	}

	/**
	 * Tests that the same processing result ID can be logged once per feed ID.
	 *
	 * @return void
	 */
	public function test_log_failed_processing_result_scopes_deduplication_by_feed_id() {
		$processing_results = $this->get_failed_processing_results();

		FeedStatusService::log_failed_processing_result( 'feed-123', $processing_results );
		FeedStatusService::log_failed_processing_result( 'feed-456', $processing_results );

		$this->assertCount( 2, $this->mock_logger->entries );
		$this->assertEquals(
			array(
				'feed-123' => 'processing-result-1',
				'feed-456' => 'processing-result-1',
			),
			Pinterest_For_Woocommerce::get_data( 'last_logged_processing_result_id' )
		);
	}

	/**
	 * Tests that another feed logging later does not allow an old result to be re-logged.
	 *
	 * @return void
	 */
	public function test_log_failed_processing_result_keeps_deduplication_per_feed_after_other_feed_logs() {
		$feed_a = $this->get_failed_processing_results();
		$feed_b = $this->get_failed_processing_results();

		$feed_b['id'] = 'processing-result-2';

		FeedStatusService::log_failed_processing_result( 'feed-123', $feed_a );
		FeedStatusService::log_failed_processing_result( 'feed-456', $feed_b );
		FeedStatusService::log_failed_processing_result( 'feed-123', $feed_a );

		$this->assertCount( 2, $this->mock_logger->entries );
		$this->assertEquals(
			array(
				'feed-123' => 'processing-result-1',
				'feed-456' => 'processing-result-2',
			),
			Pinterest_For_Woocommerce::get_data( 'last_logged_processing_result_id' )
		);
	}

	/**
	 * Tests that a non-FAILED processing result is ignored even if a result ID is present.
	 *
	 * @return void
	 */
	public function test_log_failed_processing_result_skips_when_status_is_not_failed() {
		$processing_results           = $this->get_failed_processing_results();
		$processing_results['status'] = 'COMPLETED';

		FeedStatusService::log_failed_processing_result( 'feed-123', $processing_results );

		$this->assertCount( 0, $this->mock_logger->entries );
		$this->assertEmpty( Pinterest_For_Woocommerce::get_data( 'last_logged_processing_result_id' ) );
	}

	/**
	 * Tests that an empty processing result payload (e.g. empty API response) is ignored.
	 *
	 * @return void
	 */
	public function test_log_failed_processing_result_skips_when_processing_result_id_missing() {
		FeedStatusService::log_failed_processing_result( 'feed-123', array( 'status' => 'FAILED' ) );

		$this->assertCount( 0, $this->mock_logger->entries );
		$this->assertEmpty( Pinterest_For_Woocommerce::get_data( 'last_logged_processing_result_id' ) );
	}

	/**
	 * Tests that a new failed processing result ID is logged even after a previous one was recorded.
	 *
	 * @return void
	 */
	public function test_log_failed_processing_result_logs_new_processing_result_id_after_previous() {
		$first  = $this->get_failed_processing_results();
		$second = $this->get_failed_processing_results();

		$second['id']         = 'processing-result-2';
		$second['created_at'] = '2026-05-12T07:00:00';

		FeedStatusService::log_failed_processing_result( 'feed-123', $first );
		FeedStatusService::log_failed_processing_result( 'feed-123', $second );

		$this->assertCount( 2, $this->mock_logger->entries );
		$this->assertEquals(
			array(
				'feed-123' => 'processing-result-2',
			),
			Pinterest_For_Woocommerce::get_data( 'last_logged_processing_result_id' )
		);
	}

	/**
	 * Tests that the logged feed URL belongs to the failed feed ID.
	 *
	 * @return void
	 */
	public function test_log_failed_processing_result_uses_failed_feed_url() {
		$failing_feed_url     = 'https://example.test/pinterest-for-woocommerce-feed-456.xml';
		$non_failing_feed_url = 'https://example.test/pinterest-for-woocommerce-feed-123.xml';
		$this->remote_feeds   = array(
			array(
				'id'       => 'feed-123',
				'location' => $non_failing_feed_url,
			),
			array(
				'id'       => 'feed-456',
				'location' => $failing_feed_url,
			),
		);

		FeedStatusService::log_failed_processing_result( 'feed-456', $this->get_failed_processing_results() );

		$logged_failure_message = $this->mock_logger->entries[0]['message'];

		$this->assertStringContainsString( "feed_url: {$failing_feed_url}", $logged_failure_message );
		$this->assertStringNotContainsString( "feed_url: {$non_failing_feed_url}", $logged_failure_message );
	}

	/**
	 * Tests that remote feed IDs resolve feed URLs from their registered remote location.
	 *
	 * @return void
	 */
	public function test_log_failed_processing_result_uses_registered_remote_feed_location() {
		$base_country             = Pinterest_For_Woocommerce::get_base_country();
		$remote_feed_location     = 'https://example.test/pinterest-for-woocommerce-local-feed-456.xml';
		$this->remote_feeds       = array(
			array(
				'id'       => 'remote-feed-456',
				'location' => $remote_feed_location,
			),
		);
		$processing_results       = $this->get_failed_processing_results();
		$processing_results['id'] = 'processing-result-remote-feed';

		Pinterest_For_Woocommerce::save_data(
			'local_feed_ids',
			array(
				$base_country => 'local-feed-123',
			)
		);

		FeedStatusService::log_failed_processing_result( 'remote-feed-456', $processing_results );

		$configs                = LocalFeedConfigs::get_instance()->get_configurations();
		$local_feed_url         = $configs[ $base_country ]['feed_url'];
		$logged_failure_message = $this->mock_logger->entries[0]['message'];

		$this->assertStringContainsString( "feed_url: {$remote_feed_location}", $logged_failure_message );
		$this->assertStringNotContainsString( "feed_url: {$local_feed_url}", $logged_failure_message );
	}

	/**
	 * Tests that an unresolved feed URL is logged with a sentinel value.
	 *
	 * @return void
	 */
	public function test_log_failed_processing_result_logs_unresolved_feed_url_when_no_feed_matches() {
		FeedStatusService::log_failed_processing_result( 'unknown-feed', $this->get_failed_processing_results() );

		$this->assertStringContainsString(
			'feed_url: (unresolved)',
			$this->mock_logger->entries[0]['message']
		);
	}

	/**
	 * Tests that a FETCH_ERROR failed processing result triggers one feed update retry.
	 *
	 * @return void
	 */
	public function test_maybe_retry_on_fetch_error_triggers_retry_once() {
		$processing_results = $this->get_failed_processing_results();

		$this->assertTrue( FeedStatusService::maybe_retry_on_fetch_error( 'feed-123', $processing_results ) );

		$this->assertCount( 1, $this->update_feed_requests );
		$this->assertEquals( 'PATCH', $this->update_feed_requests[0]['method'] );
		$this->assertEquals(
			'https://api.pinterest.com/v5/catalogs/feeds/feed-123?ad_account_id=114141241212',
			$this->update_feed_requests[0]['url']
		);
		$this->assertEquals( 'ACTIVE', $this->update_feed_requests[0]['body']['status'] );
		$this->assertEquals(
			'Etc/UTC',
			$this->update_feed_requests[0]['body']['preferred_processing_schedule']['timezone']
		);
		$this->assertEquals( 'RETAIL', $this->update_feed_requests[0]['body']['catalog_type'] );
		$this->assertEquals(
			'processing-result-1',
			Pinterest_For_Woocommerce::get_data( 'last_retried_processing_result_id' )
		);
		$this->assert_log_entry_contains( 'FETCH_ERROR retry triggered for processing_result_id=processing-result-1', 'info' );
	}

	/**
	 * Tests that duplicate processing result IDs are not retried twice.
	 *
	 * @return void
	 */
	public function test_maybe_retry_on_fetch_error_does_not_retry_same_processing_result_id_twice() {
		$processing_results = $this->get_failed_processing_results();

		$this->assertTrue( FeedStatusService::maybe_retry_on_fetch_error( 'feed-123', $processing_results ) );
		$this->assertFalse( FeedStatusService::maybe_retry_on_fetch_error( 'feed-123', $processing_results ) );

		$this->assertCount( 1, $this->update_feed_requests );
	}

	/**
	 * Tests that non-FETCH_ERROR failed processing results do not trigger a retry.
	 *
	 * @return void
	 */
	public function test_maybe_retry_on_fetch_error_does_not_retry_non_fetch_error_failures() {
		$processing_results                                   = $this->get_failed_processing_results();
		$processing_results['validation_details']['errors']   = array(
			'NO_VERIFIED_DOMAIN' => 1,
		);
		$processing_results['validation_details']['warnings'] = array();
		$processing_results['product_counts']['ingested']     = 0;
		$processing_results['product_counts']['original']     = 10;
		$processing_results['id']                             = 'processing-result-no-verified-domain';

		$this->assertFalse( FeedStatusService::maybe_retry_on_fetch_error( 'feed-123', $processing_results ) );

		$this->assertCount( 0, $this->update_feed_requests );
		$this->assertNull( Pinterest_For_Woocommerce::get_data( 'last_retried_processing_result_id' ) );
	}

	/**
	 * Tests that feed update exceptions are logged and swallowed.
	 *
	 * @return void
	 */
	public function test_maybe_retry_on_fetch_error_logs_and_swallows_update_exceptions() {
		$this->should_fail_update_feed_request = true;
		$processing_results                    = $this->get_failed_processing_results();

		$this->assertFalse( FeedStatusService::maybe_retry_on_fetch_error( 'feed-123', $processing_results ) );

		$this->assertCount( 1, $this->update_feed_requests );
		$this->assertNull( Pinterest_For_Woocommerce::get_data( 'last_retried_processing_result_id' ) );
		$this->assert_log_entry_contains( 'FETCH_ERROR retry failed for processing_result_id=processing-result-1: Temporary Pinterest API failure.' );
	}

	/**
	 * Intercept Pinterest API requests.
	 *
	 * @param mixed  $response    Preemptive response.
	 * @param array  $parsed_args Request arguments.
	 * @param string $url         Request URL.
	 * @return mixed
	 */
	public function intercept_api_request( $response, $parsed_args, $url ) {
		if ( 'https://api.pinterest.com/v5/catalogs/feeds?ad_account_id=114141241212' === $url ) {
			return array(
				'headers'  => array(
					'content-type' => 'application/json',
				),
				'body'     => wp_json_encode( array( 'items' => $this->remote_feeds ) ),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => '',
			);
		}

		if ( 'https://api.pinterest.com/v5/catalogs/feeds/feed-123?ad_account_id=114141241212' !== $url ) {
			return $response;
		}

		$this->update_feed_requests[] = array(
			'url'    => $url,
			'method' => $parsed_args['method'],
			'body'   => json_decode( $parsed_args['body'], true ),
		);

		if ( $this->should_fail_update_feed_request ) {
			return array(
				'headers'  => array(
					'content-type' => 'application/json',
				),
				'body'     => wp_json_encode(
					array(
						'code'    => 500,
						'message' => 'Temporary Pinterest API failure.',
					)
				),
				'response' => array(
					'code'    => 500,
					'message' => 'Internal Server Error',
				),
				'cookies'  => array(),
				'filename' => '',
			);
		}

		return array(
			'headers'  => array(
				'content-type' => 'application/json',
			),
			'body'     => wp_json_encode( array( 'id' => 'feed-123' ) ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => '',
		);
	}

	/**
	 * Get a failed processing result fixture.
	 *
	 * @return array
	 */
	private function get_failed_processing_results(): array {
		return array(
			'id'                 => 'processing-result-1',
			'status'             => 'FAILED',
			'created_at'         => '2026-05-12T06:00:00',
			'validation_details' => array(
				'errors'   => array(
					'FETCH_ERROR' => 1,
				),
				'warnings' => array(
					'IMAGE_LINK_WARNING' => 2,
				),
			),
			'product_counts'     => array(
				'original' => 10,
				'ingested' => 0,
			),
		);
	}

	/**
	 * Assert a log entry contains a given message fragment.
	 *
	 * @param string $message_fragment Message fragment.
	 * @param string $expected_level   Expected log level.
	 * @return void
	 */
	private function assert_log_entry_contains( string $message_fragment, string $expected_level = 'error' ): void {
		foreach ( $this->mock_logger->entries as $entry ) {
			if ( false !== strpos( $entry['message'], $message_fragment ) ) {
				$this->assertEquals( $expected_level, $entry['level'] );
				$this->assertEquals(
					array(
						'source' => PINTEREST_FOR_WOOCOMMERCE_LOG_PREFIX . '-feed-ingestion-failure',
					),
					$entry['handler']
				);
				return;
			}
		}

		$this->fail( "Expected log entry containing: {$message_fragment}" );
	}
}
