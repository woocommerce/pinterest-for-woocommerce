<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use ActionScheduler_Action;
use ActionScheduler_QueueRunner;
use ActionScheduler;
use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;
use Automattic\WooCommerce\Admin\Notes\Notes;
use Automattic\WooCommerce\Pinterest\Exception\FeedCircuitBreakerException;
use Automattic\WooCommerce\Pinterest\FeedFileOperations;
use Automattic\WooCommerce\Pinterest\Notes\FeedCircuitBreakerNote;
use Automattic\WooCommerce\Pinterest\FeedGenerator;
use Automattic\WooCommerce\Pinterest\LocalFeedConfigs;
use Automattic\WooCommerce\Pinterest\ProductFeedStatus;
use Exception;
use Pinterest_For_Woocommerce;
use ReflectionMethod;
use WC_Helper_Product;
use WC_Product_Variable;

/**
 * Test helper class that wraps real Action Scheduler functions.
 */
class TestActionSchedulerProxy implements ActionSchedulerInterface {
	/**
	 * Schedule an action to run immediately.
	 *
	 * @param string $hook  Action hook.
	 * @param mixed  $args  Action arguments.
	 * @param string $group Action group.
	 * @return int Action ID.
	 */
	public function schedule_immediate( string $hook, $args = array(), string $group = '' ) {
		return as_schedule_single_action( time(), $hook, $args, $group );
	}

	/**
	 * Search for scheduled actions — delegates to real Action Scheduler so status
	 * filters (e.g. STATUS_PENDING) behave exactly as they would in production.
	 *
	 * @param mixed  $args          Search arguments (see as_get_scheduled_actions()).
	 * @param string $return_format Return format: OBJECT, ARRAY_A, or 'ids'.
	 * @param string $group         Action group.
	 * @return array
	 */
	public function search( $args = array(), $return_format = OBJECT, string $group = '' ) {
		$args['group'] = $group;
		return as_get_scheduled_actions( $args, $return_format );
	}

	/**
	 * Cancel an action.
	 *
	 * @param string $hook  Action hook.
	 * @param mixed  $args  Action arguments.
	 * @param string $group Action group.
	 * @return void
	 */
	public function cancel( string $hook, $args = array(), string $group = '' ) {}

	/**
	 * Cancel all actions matching criteria.
	 *
	 * @param string $hook  Action hook.
	 * @param mixed  $args  Action arguments.
	 * @param string $group Action group.
	 * @return void
	 */
	public function cancel_all( string $hook, $args = array(), string $group = '' ) {}

	/**
	 * Schedule a single action at a given timestamp.
	 *
	 * @param mixed  $timestamp Unix timestamp.
	 * @param string $hook      Action hook.
	 * @param mixed  $args      Action arguments.
	 * @param string $group     Action group.
	 * @return int Action ID.
	 */
	public function schedule_single( $timestamp, $hook, $args = array(), string $group = '' ) {
		return as_schedule_single_action( $timestamp, $hook, $args, $group );
	}

	/**
	 * Get the next scheduled action timestamp.
	 *
	 * @param string $hook  Action hook.
	 * @param mixed  $args  Action arguments.
	 * @param string $group Action group.
	 * @return int|bool
	 */
	public function next_scheduled_action( $hook, $args = null, string $group = '' ) {
		return as_next_scheduled_action( $hook, $args, $group );
	}
}

class FeedGeneratorTest extends \WP_UnitTestCase {

	/** @var ActionSchedulerInterface */
	private $action_scheduler;

	/** @var FeedFileOperations */
	private $feed_file_operations;

	/** @var LocalFeedConfigs */
	private $local_feed_configs;

	/** @var FeedGenerator */
	private $feed_generator;

	public function setUp(): void {
		parent::setUp();
		$this->action_scheduler     = $this->createMock( ActionSchedulerInterface::class );
		$this->feed_file_operations = $this->createMock( FeedFileOperations::class );
		$this->local_feed_configs   = $this->createMock( LocalFeedConfigs::class );
		$this->local_feed_configs
			->method( 'get_configurations' )
			->willReturn( array() );

		$this->feed_generator = new FeedGenerator( $this->action_scheduler, $this->feed_file_operations, $this->local_feed_configs );

		ProductFeedStatus::set( ProductFeedStatus::STATE_PROPS );
	}

	/**
	 * Clean up Action Scheduler entries after each test to prevent cross-test interference
	 * when the deduplication guard checks for pending scheduled actions.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		as_unschedule_all_actions( 'pinterest/jobs/generate_feed/chain_batch', null, 'pinterest-for-woocommerce' );
		as_unschedule_all_actions( 'pinterest-for-woocommerce-start-feed-generation', null, 'pinterest-for-woocommerce' );
		Notes::delete_notes_with_name( FeedCircuitBreakerNote::NOTE_NAME );
		parent::tearDown();
	}

	/**
	 * Helper to invoke a protected method on FeedGenerator via reflection.
	 *
	 * @param FeedGenerator $generator    The FeedGenerator instance.
	 * @param string        $method_name  Name of the protected method.
	 * @param array         $args         Arguments to pass to the method.
	 * @return mixed Return value of the method.
	 */
	private function invoke_protected( FeedGenerator $generator, string $method_name, array $args = array() ) {
		$reflection = new \ReflectionMethod( FeedGenerator::class, $method_name );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( $generator, $args );
	}

	/**
	 * Tests feed generator registers the action scheduler failed execution hook.
	 *
	 * @return void
	 */
	public function test_init_adds_action_scheduler_failed_execution_hook() {
		$this->feed_generator->init();

		$this->assertEquals(
			10,
			has_action(
				'action_scheduler_failed_execution',
				array( $this->feed_generator, 'handle_failed_execution' )
			)
		);
	}

	/**
	 * Tests feed generator registers the action scheduler shutdown hook.
	 *
	 * @return void
	 */
	public function test_init_adds_action_scheduler_unexpected_shutdown_hook() {
		$this->feed_generator->init();

		$this->assertEquals(
			10,
			has_action(
				'action_scheduler_unexpected_shutdown',
				array( $this->feed_generator, 'handle_unexpected_shutdown' )
			)
		);
	}

	/**
	 * Tests feed generator does not reschedule other than pinterest feed generation actions.
	 *
	 * @return void
	 */
	public function test_handle_unexpected_shutdown_does_nothing_if_not_a_timeout_error() {
		$action_id = as_schedule_single_action(
			gmdate( 'U' ) - 1,
			'pinterest/jobs/generate_feed/chain_batch',
			array( 1, array() ),
			'pinterest-for-woocommerce'
		);

		$this->action_scheduler->expects( $this->never() )
			->method( 'schedule_immediate' );

		$error = array(
			'type'    => E_ERROR,
			'message' => 'A non timeout error',
		);
		$this->feed_generator->handle_unexpected_shutdown( $action_id, $error );
	}

	/**
	 * Tests feed generator does nothing if the action is not found.
	 *
	 * @return void
	 */
	public function test_handle_unexpected_shutdown_does_nothing_if_timeout_but_not_a_different_action() {
		$action_id = as_schedule_single_action(
			gmdate( 'U' ) - 1,
			'pinterest/jobs/generate_feed/chain_batch_foo',
			array( 1, array() ),
			'pinterest-for-woocommerce'
		);

		$this->action_scheduler->expects( $this->never() )
			->method( 'schedule_immediate' );

		$error = array(
			'type'    => E_ERROR,
			'message' => 'A non timeout error',
		);
		$this->feed_generator->handle_unexpected_shutdown( $action_id, $error );
	}

	/**
	 * Tests feed generator does throttle product number when rescheduling the action.
	 *
	 * @return void
	 */
	public function test_handle_unexpected_shutdown_does_throttle_product_number_when_rescheduling_the_action() {
		// Use real FeedGenerator that schedules actual actions.
		$real_feed_generator = new FeedGenerator(
			new TestActionSchedulerProxy(),
			$this->feed_file_operations,
			$this->local_feed_configs
		);

		$action_id = as_schedule_single_action(
			gmdate( 'U' ) - 1,
			'pinterest/jobs/generate_feed/chain_batch',
			array( 1, array() ),
			'pinterest-for-woocommerce'
		);
		// Simulate Action Scheduler picking up the action: mark it in-progress exactly
		// as AS does via process_action() → log_execution() before invoking our callback.
		// This mirrors real timeout conditions and ensures the dedup guard (STATUS_PENDING
		// only) allows the first reschedule even while the original action is running.
		\ActionScheduler_Store::instance()->log_execution( $action_id );

		$error = array(
			'type'    => E_ERROR,
			'message' => 'Maximum execution time',
		);

		// First call: should reschedule and throttle.
		$real_feed_generator->handle_unexpected_shutdown( $action_id, $error );
		$this->assertEquals( 50, \Pinterest_For_Woocommerce::get_data( 'feed_product_batch_size' ) );
		$this->assertEquals( 2, \Pinterest_For_Woocommerce::get_data( 'feed_product_batch_attempt' ) );

		// Second call: should be skipped due to deduplication (action already scheduled from first call).
		$real_feed_generator->handle_unexpected_shutdown( $action_id, $error );

		// Batch size and attempt should remain the same since rescheduling was skipped.
		$this->assertEquals( 50, \Pinterest_For_Woocommerce::get_data( 'feed_product_batch_size' ) );
		$this->assertEquals( 2, \Pinterest_For_Woocommerce::get_data( 'feed_product_batch_attempt' ) );
	}

	/**
	 * Tests that deduplication prevents rescheduling when action is already scheduled.
	 *
	 * @return void
	 */
	public function test_handle_unexpected_shutdown_skips_rescheduling_if_action_already_scheduled() {
		// Use real FeedGenerator that actually schedules actions.
		$real_feed_generator = new FeedGenerator(
			new TestActionSchedulerProxy(),
			$this->feed_file_operations,
			$this->local_feed_configs
		);

		$action_id = as_schedule_single_action(
			gmdate( 'U' ) - 1,
			'pinterest/jobs/generate_feed/chain_batch',
			array( 1, array() ),
			'pinterest-for-woocommerce'
		);
		// Simulate Action Scheduler picking up the action: mark it in-progress exactly
		// as AS does via process_action() → log_execution() before invoking our callback.
		\ActionScheduler_Store::instance()->log_execution( $action_id );

		$error = array(
			'type'    => E_ERROR,
			'message' => 'Maximum execution time',
		);

		// First timeout: should reschedule and decrease batch size.
		$real_feed_generator->handle_unexpected_shutdown( $action_id, $error );
		$this->assertEquals( 50, \Pinterest_For_Woocommerce::get_data( 'feed_product_batch_size' ) );

		// Second timeout: deduplication should prevent rescheduling since action already exists.
		$real_feed_generator->handle_unexpected_shutdown( $action_id, $error );

		// Batch size should remain unchanged since deduplication skipped the second reschedule.
		$this->assertEquals( 50, \Pinterest_For_Woocommerce::get_data( 'feed_product_batch_size' ) );
	}

	/**
	 * Tests if the feed generator reschedules the action if the failure threshold is not met.
	 *
	 * @return void
	 */
	public function test_handle_unexpected_shutdown_does_not_reschedule_the_action_if_failure_threshold_met() {
		$this->action_scheduler->expects( $this->never() )
			->method( 'schedule_immediate' );
		// We do not care about the content, but we must return the number of elements in array.
		$this->action_scheduler->method( 'search' )
			->willReturn( array_fill( 0, 3, 1 ) );

		$action_id = as_schedule_single_action(
			gmdate( 'U' ) - 1,
			'pinterest/jobs/generate_feed/chain_batch',
			array( 1, array() ),
			'pinterest-for-woocommerce'
		);

		$error = array(
			'type'    => E_ERROR,
			'message' => 'Maximum execution time',
		);
		$this->feed_generator->handle_unexpected_shutdown( $action_id, $error );
	}

	/**
	 * Tests if a successfully executed action resets the batch size and batch attempts data key values.
	 *
	 * @return void
	 */
	public function test_successful_action_execution_resets_batch_size_and_batch_attempts_data_key_values() {
		Pinterest_For_Woocommerce::save_data( 'feed_product_batch_size', 345 );
		Pinterest_For_Woocommerce::save_data( 'feed_product_batch_attempt', 15239 );

		$this->feed_generator->handle_batch_action( 1, array() );

		$this->assertNull( Pinterest_For_Woocommerce::get_data( 'feed_product_batch_size' ) );
		$this->assertNull( Pinterest_For_Woocommerce::get_data( 'feed_product_batch_attempt' ) );
	}

	/**
	 * Tests handler does nothing if the action is not found.
	 *
	 * @return void
	 */
	public function test_handle_failed_execution_does_nothing_if_a_different_action() {
		$store     = ActionScheduler::store();
		$action_id = as_schedule_single_action(
			gmdate( 'U' ) - 1,
			'pinterest/jobs/generate_feed/chain_batch_foo',
			array( 1, array() ),
			'pinterest-for-woocommerce'
		);
		$store->mark_failure( $action_id );

		$this->feed_generator->handle_failed_execution( $action_id, new Exception( 'Some error msg.' ), '' );

		$pending_actions = as_get_scheduled_actions(
			array(
				'hook'   => 'pinterest/jobs/generate_feed/chain_batch_foo',
				'status' => 'pending',
			)
		);

		$this->assertCount( 0, $pending_actions );
	}

	/**
	 * Tests that the feed generator reschedules itself when the feed file operations fail with exception.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function test_action_scheduler_failed_execution_hook_calls_handle_failed_execution() {
		$store  = ActionScheduler::store();
		$runner = new ActionScheduler_QueueRunner( $store );

		add_action( 'action_scheduler_failed_execution', array( $this->feed_generator, 'handle_failed_execution' ), 10, 2 );

		// Add a callback to throw an exception when the action is processed.
		$callback = function () {
			throw new Exception( 'Action `pinterest/jobs/generate_feed/chain_batch` failed to complete.' );
		};
		add_action( 'pinterest/jobs/generate_feed/chain_batch', $callback, 10, 2 );

		as_schedule_single_action(
			gmdate( 'U' ) - 1,
			'pinterest/jobs/generate_feed/chain_batch',
			array( 1, array() ),
			'pinterest-for-woocommerce'
		);

		$runner->run();

		remove_action( 'pinterest/jobs/generate_feed/chain_batch', $callback );

		// Check feed generation status.
		list(
			'status'                    => $status,
			'error_message'             => $error_message,
			'feed_generation_wall_time' => $feed_generation_wall_time,
		) = ProductFeedStatus::get();
		$this->assertEquals( 'error', $status );
		$this->assertEquals( 'Action `pinterest/jobs/generate_feed/chain_batch` failed to complete.', $error_message );
		$this->assertEquals( -1, $feed_generation_wall_time );

		// Check the next scheduled action.
		$future_actions = as_get_scheduled_actions(
			array(
				'hook'   => 'pinterest-for-woocommerce-start-feed-generation',
				'status' => 'pending',
				'group'  => 'pinterest-for-woocommerce',
			)
		);

		$this->assertCount( 1, $future_actions );
		/** @var ActionScheduler_Action $action */
		$action         = current( $future_actions );
		$delay_in_hours = (int) ceil( ( $action->get_schedule()->get_date()->getTimestamp() - time() ) / 3600 );
		$this->assertEquals( 1, $delay_in_hours );
	}

	public function test_feed_generator_start_sets_product_feed_status_generation_start_time() {
		$time_test_started = time();

		$this->feed_generator->handle_start_action( array() );

		$feed_generation_wall_start_time = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_START_TIME ];
		$feed_generation_wall_time       = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];

		$this->assertGreaterThanOrEqual( $time_test_started, $feed_generation_wall_start_time );
		$this->assertEquals( 0, $feed_generation_wall_time );
	}

	/**
	 * When new feed generation starts, make sure not to reset previous run stats like total wall time it took to generate
	 * the feed and a number of products that feed had.
	 *
	 * @return void
	 */
	public function test_feed_generator_start_does_not_reset_recent_product_count_and_wall_time() {
		$time_test_started = time();
		ProductFeedStatus::set(
			array(
				ProductFeedStatus::PROP_FEED_GENERATION_WALL_START_TIME      => 61461453,
				ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT => 123,
				ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME            => 76823678,
			)
		);

		$this->feed_generator->handle_start_action( array() );

		$feed_generation_wall_start_time = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_START_TIME ];
		$feed_generation_wall_time       = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];
		$feed_generation_product_count   = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT ];

		$this->assertGreaterThanOrEqual( $time_test_started, $feed_generation_wall_start_time );
		$this->assertEquals( 123, $feed_generation_product_count );
		$this->assertEquals( 76823678, $feed_generation_wall_time );
	}

	public function test_feed_generator_start_fails_and_exception_is_thrown() {
		$this->expectException( Exception::class );
		$this->feed_file_operations
			->method( 'prepare_temporary_files' )
			->willThrowException( new Exception() );

		$this->feed_generator->handle_start_action( array() );
	}

	public function test_feed_generator_start_fails_and_sets_wall_time_to_negative() {
		$this->feed_file_operations
			->method( 'prepare_temporary_files' )
			->willThrowException( new Exception() );

		try {
			$this->feed_generator->handle_start_action( array() );
		} catch ( Exception $e ) {
			$feed_generation_status        = ProductFeedStatus::get()['status'];
			$feed_generation_wall_time     = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];
			$feed_generation_product_count = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT ];

			$this->assertEquals( 'error', $feed_generation_status );
			$this->assertEquals( 0, $feed_generation_product_count );
			$this->assertEquals( -1, $feed_generation_wall_time );
		}
	}

	public function test_feed_generator_end_sets_time_it_took_to_generate_the_feed() {
		$time_test_started = time();
		ProductFeedStatus::set(
			array(
				ProductFeedStatus::PROP_FEED_GENERATION_WALL_START_TIME => 0,
			)
		);

		$this->feed_generator->handle_end_action( array() );

		$feed_generation_wall_time = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];

		$this->assertGreaterThanOrEqual( $time_test_started, $feed_generation_wall_time );
	}

	public function test_feed_generator_end_sets_product_count_into_persistent_state_property() {
		ProductFeedStatus::set(
			array(
				'product_count' => 13,
				ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT => 1,
			)
		);

		$this->feed_generator->handle_end_action( array() );

		$feed_generation_product_count = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT ];

		$this->assertEquals( 13, $feed_generation_product_count );
	}

	public function test_feed_generator_end_fails_and_exception_is_thrown() {
		$this->expectException( Exception::class );
		$this->feed_file_operations
			->method( 'add_footer_to_temporary_feed_files' )
			->willThrowException( new Exception() );

		$this->feed_generator->handle_end_action( array() );
	}

	public function test_feed_generator_end_fails_and_sets_wall_time_to_negative() {
		$this->feed_file_operations
			->method( 'add_footer_to_temporary_feed_files' )
			->willThrowException( new Exception() );

		try {
			$this->feed_generator->handle_end_action( array() );
		} catch ( Exception $e ) {
			$feed_generation_status        = ProductFeedStatus::get()['status'];
			$feed_generation_wall_time     = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];
			$feed_generation_product_count = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT ];

			$this->assertEquals( 'error', $feed_generation_status );
			$this->assertEquals( 0, $feed_generation_product_count );
			$this->assertEquals( -1, $feed_generation_wall_time );
		}
	}

	/**
	 * Content integrity guard — normal cases that must NOT throw.
	 *
	 * @dataProvider feed_integrity_pass_provider
	 */
	public function test_feed_content_integrity_passes( int $written, int $published ): void {
		// Create exactly $published simple products so count_published_products() returns $published.
		for ( $i = 0; $i < $published; $i++ ) {
			WC_Helper_Product::create_simple_product();
		}

		ProductFeedStatus::set( array( 'product_count' => $written ) );

		// handle_end_action must complete without throwing.
		$this->feed_generator->handle_end_action( array() );
		$this->assertTrue( true ); // reached without exception
	}

	public function feed_integrity_pass_provider(): array {
		return array(
			'exact match'                       => array( 3, 3 ),
			'written less (filtered out stock)' => array( 2, 3 ),
			'written at 1.0x'                   => array( 10, 10 ),
			'written at 1.4x (under threshold)' => array( 14, 10 ),
			'written at exactly 1.5x'           => array( 15, 10 ),
			'empty catalog skips check'         => array( 0, 0 ),
		);
	}

	public function test_feed_content_integrity_throws_when_written_count_exceeds_ratio(): void {
		$product = WC_Helper_Product::create_simple_product();

		// Simulate the cursor-reset duplication: 4 full traversals of a 1-product catalog.
		ProductFeedStatus::set( array( 'product_count' => 4 ) );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/Feed content integrity check failed/' );

		$this->feed_generator->handle_end_action( array() );

		$product->delete( true );
	}

	public function test_feed_content_integrity_throws_sets_status_to_error(): void {
		WC_Helper_Product::create_simple_product();

		// Simulate 3x duplication.
		ProductFeedStatus::set( array( 'product_count' => 3 ) );

		try {
			$this->feed_generator->handle_end_action( array() );
		} catch ( \RuntimeException $e ) {
			$this->assertEquals( 'error', ProductFeedStatus::get()['status'] );
			return;
		}

		$this->fail( 'Expected RuntimeException was not thrown.' );
	}

	public function test_while_feed_generator_is_in_progress_previous_wall_time_and_recent_product_count_are_not_overwritten() {
		ProductFeedStatus::set(
			array(
				ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME            => 19,
				ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT => 99,
			)
		);

		$this->feed_generator->handle_start_action( array() );

		$status        = ProductFeedStatus::get()['status'];
		$wall_time     = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];
		$product_count = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT ];

		$this->assertEquals( 'in_progress', $status );
		$this->assertEquals( 19, $wall_time );
		$this->assertEquals( 99, $product_count );
	}

	public function test_handle_batch_action_ends_queue_when_no_more_items() {
		$this->action_scheduler
			->expects( $this->once() )
			->method( 'schedule_immediate' )
			->with(
				'pinterest/jobs/generate_feed/chain_end',
				array( array() ),
				PINTEREST_FOR_WOOCOMMERCE_PREFIX
			);

		$this->feed_generator->handle_batch_action( 1, array() );

		$this->assertEquals( 0, (int) \Pinterest_For_Woocommerce::get_data( 'feed_generation_retries' ) );
	}

	public function test_handle_batch_action_queues_next_batch_when_there_are_items_to_process() {
		WC_Helper_Product::create_simple_product();

		$this->action_scheduler
			->expects( $this->once() )
			->method( 'schedule_immediate' )
			->with(
				'pinterest/jobs/generate_feed/chain_batch',
				array( 2, array() ),
				PINTEREST_FOR_WOOCOMMERCE_PREFIX
			);

		$this->feed_generator->handle_batch_action( 1, array() );

		$this->assertEquals( 0, (int) \Pinterest_For_Woocommerce::get_data( 'feed_generation_retries' ) );
	}

	/**
	 * Tests circuit breaker stops batch processing after reaching MAX_BATCHES_PER_CYCLE.
	 *
	 * @return void
	 */
	public function test_circuit_breaker_stops_processing_at_max_batches() {
		$product = WC_Helper_Product::create_simple_product();
		Pinterest_For_Woocommerce::save_data( 'feed_last_queued_item_id', 0 );
		// Large batch size so the single product falls inside the boundary batch's query.
		Pinterest_For_Woocommerce::save_data( 'feed_product_batch_size', 10000 );

		// The last allowed batch ( batch_number === MAX_BATCHES_PER_CYCLE ) must process
		// normally and actually return the product — not a vacuous empty array.
		$items = $this->invoke_protected( $this->feed_generator, 'get_items_for_batch', array( FeedGenerator::MAX_BATCHES_PER_CYCLE, array() ) );
		$this->assertContains( $product->get_id(), $items, 'Last allowed batch must still fetch products.' );

		// The next batch exceeds the limit and must throw so the feed is never silently truncated.
		$this->expectException( FeedCircuitBreakerException::class );
		$this->invoke_protected( $this->feed_generator, 'get_items_for_batch', array( FeedGenerator::MAX_BATCHES_PER_CYCLE + 1, array() ) );
	}

	/**
	 * Tests the circuit breaker honours a custom limit set via the filter.
	 *
	 * @return void
	 */
	public function test_circuit_breaker_respects_custom_filter_limit() {
		$filter = static function () {
			return 1;
		};
		add_filter( 'pinterest_for_woocommerce_max_feed_batches_per_cycle', $filter );

		try {
			// Batch 1 is within the custom limit and must not throw.
			$items = $this->invoke_protected( $this->feed_generator, 'get_items_for_batch', array( 1, array() ) );
			$this->assertIsArray( $items );

			// Batch 2 exceeds the custom limit of 1 and must throw.
			$this->expectException( FeedCircuitBreakerException::class );
			$this->invoke_protected( $this->feed_generator, 'get_items_for_batch', array( 2, array() ) );
		} finally {
			remove_filter( 'pinterest_for_woocommerce_max_feed_batches_per_cycle', $filter );
		}
	}

	/**
	 * Tests that cursor is only advanced after successful batch processing.
	 *
	 * @return void
	 */
	public function test_cursor_deferred_until_successful_batch_completion() {
		// Create a product to fetch.
		WC_Helper_Product::create_simple_product();

		// Set initial cursor via the dedicated option (cursor moved from shared data option).
		update_option( FeedGenerator::FEED_CURSOR_OPTION, 0 );

		// Fetch items - this should store pending cursor but not commit it yet.
		$items = $this->invoke_protected( $this->feed_generator, 'get_items_for_batch', array( 1, array() ) );
		$this->assertNotEmpty( $items );

		// Cursor should still be at initial value (not advanced yet).
		$cursor_before = (int) get_option( FeedGenerator::FEED_CURSOR_OPTION, 0 );
		$this->assertEquals( 0, $cursor_before, 'Cursor should not advance before handle_batch_action completes' );

		// Complete batch processing.
		$this->feed_generator->handle_batch_action( 1, array() );

		// Now cursor should be advanced.
		$cursor_after = (int) get_option( FeedGenerator::FEED_CURSOR_OPTION, 0 );
		$this->assertGreaterThan( $cursor_before, $cursor_after, 'Cursor should advance after successful batch completion' );
	}

	/**
	 * Tests get feed products method returns products in stock including products on backorder.
	 *
	 * @return void
	 */
	public function test_get_feed_products_return_backorder_enabled_products() {
		update_option( 'woocommerce_hide_out_of_stock_items', 'yes' );
		$product_a = \WC_Helper_Product::create_simple_product(
			true,
			array(
				'name' => 'In stock product',
			)
		);
		$product_b = \WC_Helper_Product::create_simple_product(
			true,
			array(
				'name'         => 'Product on backorder',
				'stock_status' => 'onbackorder',
			)
		);
		$product_c = \WC_Helper_Product::create_simple_product(
			true,
			array(
				'name'         => 'Out of stock product',
				'stock_status' => 'outofstock',
			)
		);

		$ids = array( $product_a->get_id(), $product_b->get_id(), $product_c->get_id() );

		$products = $this->feed_generator->get_feed_products( $ids );

		$this->assertCount( 2, $products );
		$this->assertEquals( $product_a->get_id(), $products[0]->get_id() );
		$this->assertEquals( 'In stock product', $products[0]->get_name() );
		$this->assertEquals( 'instock', $products[0]->get_stock_status() );
		$this->assertEquals( $product_b->get_id(), $products[1]->get_id() );
		$this->assertEquals( 'Product on backorder', $products[1]->get_name() );
		$this->assertEquals( 'onbackorder', $products[1]->get_stock_status() );
	}

	/**
	 * Helper to call the protected get_items_for_batch method via reflection.
	 *
	 * @param int   $batch_number The batch number.
	 * @param array $args        The args for the job.
	 *
	 * @return array Product IDs.
	 */
	private function call_get_items_for_batch( int $batch_number = 1, array $args = array() ): array {
		// Use a large batch size to ensure all test products are included regardless of
		// any products created by the test bootstrap or other setup routines.
		Pinterest_For_Woocommerce::save_data( 'feed_product_batch_size', 10000 );

		$method = new ReflectionMethod( FeedGenerator::class, 'get_items_for_batch' );
		$method->setAccessible( true );
		return $method->invoke( $this->feed_generator, $batch_number, $args );
	}

	/**
	 * Tests that variations of a variable product are included in the feed.
	 *
	 * @return void
	 */
	public function test_get_items_for_batch_includes_variations_of_variable_products() {
		$product           = new WC_Product_Variable();
		$variation_product = WC_Helper_Product::create_variation_product( $product );
		$variation_ids     = $variation_product->get_children();

		$batch_ids = $this->call_get_items_for_batch();

		foreach ( $variation_ids as $variation_id ) {
			$this->assertContains( $variation_id, $batch_ids );
		}
	}

	/**
	 * Tests that variations whose parent is not a variable product are excluded from the feed.
	 * This covers the case where a variable product is converted to a simple product,
	 * leaving behind orphaned variations in the database.
	 *
	 * @return void
	 */
	public function test_get_items_for_batch_excludes_orphaned_variations() {
		// Create a variable product with variations.
		$product           = new WC_Product_Variable();
		$variation_product = WC_Helper_Product::create_variation_product( $product );
		$variation_ids     = $variation_product->get_children();
		$parent_id         = $variation_product->get_id();

		// Convert the parent from variable to simple, orphaning the variations.
		wp_set_object_terms( $parent_id, 'simple', 'product_type' );

		$batch_ids = $this->call_get_items_for_batch();

		// The parent (now simple) should be included.
		$this->assertContains( $parent_id, $batch_ids );

		// The orphaned variations should be excluded.
		foreach ( $variation_ids as $variation_id ) {
			$this->assertNotContains( $variation_id, $batch_ids );
		}
	}

	/**
	 * Tests that simple products are included in the feed batch.
	 *
	 * @return void
	 */
	public function test_get_items_for_batch_includes_simple_products() {
		$product = WC_Helper_Product::create_simple_product();

		$batch_ids = $this->call_get_items_for_batch();

		$this->assertContains( $product->get_id(), $batch_ids );
	}

	/**
	 * Tests that draft products are excluded from the feed batch.
	 *
	 * @return void
	 */
	public function test_get_items_for_batch_excludes_draft_products() {
		$product = WC_Helper_Product::create_simple_product(
			true,
			array(
				'status' => 'draft',
			)
		);

		$batch_ids = $this->call_get_items_for_batch();

		$this->assertNotContains( $product->get_id(), $batch_ids );
	}

	/**
	 * Tests that variations with an unpublished parent are excluded from the feed.
	 *
	 * @return void
	 */
	public function test_get_items_for_batch_excludes_variations_with_draft_parent() {
		$product = new WC_Product_Variable();
		$product->set_status( 'draft' );
		$variation_product = WC_Helper_Product::create_variation_product( $product );
		$variation_ids     = $variation_product->get_children();

		$batch_ids = $this->call_get_items_for_batch();

		foreach ( $variation_ids as $variation_id ) {
			$this->assertNotContains( $variation_id, $batch_ids );
		}
	}

	/**
	 * Verifies FeedCircuitBreakerException is throwable and is-a Exception.
	 *
	 * @return void
	 */
	public function test_feed_circuit_breaker_exception_is_an_exception() {
		$this->expectException( FeedCircuitBreakerException::class );
		throw new FeedCircuitBreakerException( 'limit reached' );
	}

	/**
	 * @return void
	 */
	public function test_count_published_products_counts_only_published() {
		$before = $this->invoke_protected( $this->feed_generator, 'count_published_products', array() );

		// Published product — should be counted.
		WC_Helper_Product::create_simple_product();

		// Draft product — should NOT be counted.
		$draft = WC_Helper_Product::create_simple_product();
		wp_update_post(
			array(
				'ID'          => $draft->get_id(),
				'post_status' => 'draft',
			)
		);

		$after = $this->invoke_protected( $this->feed_generator, 'count_published_products', array() );

		$this->assertEquals( $before + 1, $after, 'Only published products should be counted' );
	}

	/**
	 * @dataProvider provide_product_count_to_recommended_limit
	 * @param int $total    Total product count.
	 * @param int $expected Expected recommended limit.
	 * @return void
	 */
	public function test_calculate_recommended_batch_limit( int $total, int $expected ) {
		$result = $this->invoke_protected(
			$this->feed_generator,
			'calculate_recommended_batch_limit',
			array( $total )
		);
		$this->assertEquals( $expected, $result );
	}

	/**
	 * @return array<string, array{int, int}>
	 */
	public function provide_product_count_to_recommended_limit(): array {
		return array(
			'0 products'    => array( 0, 500 ),
			'50k products'  => array( 50000, 1000 ),
			'120k products' => array( 120000, 1500 ),
			'200k products' => array( 200000, 2500 ),
			'500k products' => array( 500000, 6500 ),
		);
	}

	/**
	 * @return void
	 */
	public function test_handle_error_with_circuit_breaker_exception_creates_inbox_note() {
		$exception = new FeedCircuitBreakerException( 'limit reached' );
		$this->invoke_protected( $this->feed_generator, 'handle_error', array( $exception, 'chain_batch' ) );

		$note_ids = Notes::load_data_store()->get_notes_with_name( FeedCircuitBreakerNote::NOTE_NAME );
		$this->assertNotEmpty( $note_ids, 'Inbox note should be created when the circuit breaker trips' );
	}

	/**
	 * @return void
	 */
	public function test_handle_error_with_generic_exception_does_not_create_inbox_note() {
		$exception = new \Exception( 'some other error' );
		$this->invoke_protected( $this->feed_generator, 'handle_error', array( $exception, 'chain_batch' ) );

		$note_ids = Notes::load_data_store()->get_notes_with_name( FeedCircuitBreakerNote::NOTE_NAME );
		$this->assertCount( 0, $note_ids, 'No inbox note should be created for non-circuit-breaker errors' );
	}

	/**
	 * The circuit breaker must NOT reschedule a full regeneration — that would re-process
	 * the over-limit catalog every cycle and trip the breaker again indefinitely.
	 *
	 * @return void
	 */
	public function test_handle_error_with_circuit_breaker_exception_does_not_schedule_retry() {
		as_unschedule_all_actions( 'pinterest-for-woocommerce-start-feed-generation', array(), 'pinterest-for-woocommerce' );

		$this->invoke_protected(
			$this->feed_generator,
			'handle_error',
			array( new FeedCircuitBreakerException( 'limit reached' ), 'chain_batch' )
		);

		$pending = as_get_scheduled_actions(
			array(
				'hook'   => 'pinterest-for-woocommerce-start-feed-generation',
				'status' => 'pending',
				'group'  => 'pinterest-for-woocommerce',
			)
		);
		$this->assertCount( 0, $pending, 'Circuit breaker must not schedule a full regeneration retry.' );
	}

	/**
	 * A transient (non-circuit-breaker) error must still schedule a full regeneration retry.
	 *
	 * @return void
	 */
	public function test_handle_error_with_generic_exception_schedules_retry() {
		as_unschedule_all_actions( 'pinterest-for-woocommerce-start-feed-generation', array(), 'pinterest-for-woocommerce' );

		$this->invoke_protected(
			$this->feed_generator,
			'handle_error',
			array( new \Exception( 'transient error' ), 'chain_batch' )
		);

		$pending = as_get_scheduled_actions(
			array(
				'hook'   => 'pinterest-for-woocommerce-start-feed-generation',
				'status' => 'pending',
				'group'  => 'pinterest-for-woocommerce',
			)
		);
		$this->assertNotEmpty( $pending, 'Transient errors must schedule a full regeneration retry.' );
	}

	/**
	 * When the product count is unavailable or low, the recommended limit must still
	 * exceed the limit that just tripped — otherwise the note advises a useless value.
	 *
	 * @return void
	 */
	public function test_circuit_breaker_recommendation_exceeds_tripped_limit() {
		// Empty product table => count is 0 => the raw formula yields 500, which is below
		// the default tripped limit of 1000. The floor guard must bump it above 1000.
		$this->invoke_protected(
			$this->feed_generator,
			'handle_error',
			array( new FeedCircuitBreakerException( 'limit reached' ), 'chain_batch' )
		);

		$note = Notes::get_note(
			current( Notes::load_data_store()->get_notes_with_name( FeedCircuitBreakerNote::NOTE_NAME ) )
		);

		// Default MAX_BATCHES_PER_CYCLE is 1000; the floor guard bumps the recommendation to 2000.
		$this->assertStringContainsString( '2000', $note->get_content() );
	}

	/**
	 * Action Scheduler's queue runner catches the original Throwable and re-throws a generic
	 * Exception, exposing the original only via getPrevious(). handle_error must still detect
	 * the circuit breaker through the exception chain — to add the note and skip the retry.
	 *
	 * @return void
	 */
	public function test_handle_error_detects_circuit_breaker_wrapped_by_action_scheduler() {
		as_unschedule_all_actions( 'pinterest-for-woocommerce-start-feed-generation', array(), 'pinterest-for-woocommerce' );

		// Mirror ActionScheduler_Abstract_QueueRunner: throw new Exception( msg, code, $original ).
		$wrapped = new \Exception( 'limit reached', 0, new FeedCircuitBreakerException( 'limit reached' ) );
		$this->invoke_protected( $this->feed_generator, 'handle_error', array( $wrapped, 'chain_batch' ) );

		$note_ids = Notes::load_data_store()->get_notes_with_name( FeedCircuitBreakerNote::NOTE_NAME );
		$this->assertNotEmpty( $note_ids, 'Note must be created even when the breaker exception is wrapped by Action Scheduler.' );

		$pending = as_get_scheduled_actions(
			array(
				'hook'   => 'pinterest-for-woocommerce-start-feed-generation',
				'status' => 'pending',
				'group'  => 'pinterest-for-woocommerce',
			)
		);
		$this->assertCount( 0, $pending, 'A wrapped circuit breaker exception must not schedule a full regeneration retry.' );
	}
}
