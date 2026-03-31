<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use ActionScheduler_Action;
use ActionScheduler_QueueRunner;
use ActionScheduler;
use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;
use Automattic\WooCommerce\Pinterest\FeedFileOperations;
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
	 * Search for scheduled actions.
	 *
	 * @param mixed  $args          Search arguments.
	 * @param string $return_format Return format.
	 * @return array Empty array for testing.
	 */
	public function search( $args = array(), $return_format = OBJECT ) {
		return array();
	}

	/**
	 * Cancel an action.
	 *
	 * @param mixed $action_id Action ID.
	 * @return void
	 */
	public function cancel( $action_id ) {}

	/**
	 * Cancel all actions matching criteria.
	 *
	 * @param string $hook  Action hook.
	 * @param mixed  $args  Action arguments.
	 * @param string $group Action group.
	 * @return void
	 */
	public function cancel_all( string $hook, $args = array(), string $group = '' ) {}
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
}
