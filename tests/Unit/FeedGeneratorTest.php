<?php

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;

class FeedGeneratorTest extends \WP_UnitTestCase {

	/** @var ActionSchedulerInterface */
	private $action_scheduler;

	/** @var LocalFeedConfigs */
	private $local_feed_configs;

	public function setUp() {
		parent::setUp();
		$this->action_scheduler   = $this->createMock( ActionSchedulerInterface::class );
		$this->local_feed_configs = $this->createMock( LocalFeedConfigs::class );
		$this->local_feed_configs->method( 'get_configurations' )->will( $this->returnValue( [] ) );
	}

	function test_feed_generator_handle_start_action_sets_product_feed_status_with_the_feed_generation_start_and_total_time_initial_values() {
		$time_test_started = time();
		ProductFeedStatus::deregister();

		$feed_generator = new FeedGenerator( $this->action_scheduler, $this->local_feed_configs );
		$feed_generator->handle_start_action( [] );

		$feed_generation_wall_start_time = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_START_TIME ];
		$feed_generation_wall_time       = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];

		$this->assertGreaterThanOrEqual( $time_test_started, $feed_generation_wall_start_time );
		$this->assertEquals( 0, $feed_generation_wall_time );
	}

	function test_feed_generator_handle_end_action_sets_product_feed_status_with_the_time_it_took_to_generate_a_feed() {
		$time_test_started = time();
		ProductFeedStatus::deregister();
		ProductFeedStatus::set(
			array(
				ProductFeedStatus::PROP_FEED_GENERATION_WALL_START_TIME => 0,
			)
		);

		$feed_generator = new FeedGenerator( $this->action_scheduler, $this->local_feed_configs );
		$feed_generator->handle_end_action( [] );

		$feed_generation_wall_time = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];

		$this->assertGreaterThanOrEqual( $time_test_started, $feed_generation_wall_time );
	}

	function test_feed_generator_handle_end_action_sets_no_product_feed_status_with_the_time_it_took_to_generate_a_feed() {
		ProductFeedStatus::deregister();

		$feed_generator = new FeedGenerator( $this->action_scheduler, $this->local_feed_configs );
		$feed_generator->handle_end_action( [] );

		$feed_generation_wall_time = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];
		$this->assertFalse( $feed_generation_wall_time );
	}
}
