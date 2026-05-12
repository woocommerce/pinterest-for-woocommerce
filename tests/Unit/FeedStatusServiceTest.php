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
	 * Set up the WC logger mock and local feed configuration.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		LocalFeedConfigs::deregister();
		Pinterest_For_Woocommerce::set_default_settings();
		Pinterest_For_Woocommerce::save_setting( 'enable_debug_logging', false );
		Pinterest_For_Woocommerce::save_data(
			'local_feed_ids',
			array(
				Pinterest_For_Woocommerce::get_base_country() => 'local-feed',
			)
		);
		Pinterest_For_Woocommerce::remove_data( 'last_logged_processing_result_id' );

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
	}

	/**
	 * Reset logger state.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		Logger::$logger = null;
		Pinterest_For_Woocommerce::remove_data( 'last_logged_processing_result_id' );
		LocalFeedConfigs::deregister();

		parent::tearDown();
	}

	/**
	 * Tests that the full failed processing context is logged at error level.
	 *
	 * @return void
	 */
	public function test_log_failed_processing_result_logs_expected_payload_without_debug_logging_enabled() {
		$processing_results = $this->get_failed_processing_results();

		FeedStatusService::log_failed_processing_result( 'feed-123', $processing_results );

		$this->assertCount( 1, $this->mock_logger->entries );

		$entry             = $this->mock_logger->entries[0];
		$expected_feed_url = trailingslashit( wp_get_upload_dir()['baseurl'] ) .
			PINTEREST_FOR_WOOCOMMERCE_LOG_PREFIX . '-local-feed.xml';
		$expected_message  = implode(
			"\n",
			array(
				'Feed ingestion FAILED',
				'feed_id: feed-123',
				'processing_result_id: processing-result-1',
				'created_at: 2026-05-12T06:00:00',
				"feed_url: {$expected_feed_url}",
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
			'processing-result-1',
			Pinterest_For_Woocommerce::get_data( 'last_logged_processing_result_id' )
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
}
