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

	function test_feed_generator_handle_start_action_sets_transient_with_the_feed_generation_start_and_total_time_initial_values() {
		delete_transient( TrackerSnapshot::TRANSIENT_WCTRACKER_FEED_GENERATION_WALL_START_TIME );
		delete_transient( TrackerSnapshot::TRANSIENT_WCTRACKER_FEED_GENERATION_WALL_TIME );

		$feed_generator = new FeedGenerator( $this->action_scheduler, $this->local_feed_configs );
		$feed_generator->handle_start_action( [] );

		/* More or less a condition to check against. Unlikely Unit tests will ever take an hour to run. */
		$an_hour_ago = time() - 3600;
		$this->assertGreaterThan( $an_hour_ago, get_transient( TrackerSnapshot::TRANSIENT_WCTRACKER_FEED_GENERATION_WALL_START_TIME ) );
		$this->assertEquals( 0, get_transient( TrackerSnapshot::TRANSIENT_WCTRACKER_FEED_GENERATION_WALL_START_TIME ) );
	}

	function test_feed_generator_handle_end_action_sets_transient_with_the_time_it_took_to_generate_a_feed() {
		set_transient( TrackerSnapshot::TRANSIENT_WCTRACKER_FEED_GENERATION_WALL_START_TIME, 0 );
		delete_transient( TrackerSnapshot::TRANSIENT_WCTRACKER_FEED_GENERATION_WALL_TIME );

		$feed_generator = new FeedGenerator( $this->action_scheduler, $this->local_feed_configs );
		$feed_generator->handle_end_action( [] );

		/* More or less a condition to check against. Unlikely Unit tests will ever take an hour to run. */
		$an_hour_ago = time() - 3600;
		$this->assertGreaterThan( $an_hour_ago, get_transient( TrackerSnapshot::TRANSIENT_WCTRACKER_FEED_GENERATION_WALL_TIME ) );
	}

	function test_feed_generator_handle_end_action_sets_no_transient_with_the_time_it_took_to_generate_a_feed() {
		delete_transient( TrackerSnapshot::TRANSIENT_WCTRACKER_FEED_GENERATION_WALL_START_TIME );
		delete_transient( TrackerSnapshot::TRANSIENT_WCTRACKER_FEED_GENERATION_WALL_TIME );

		$feed_generator = new FeedGenerator( $this->action_scheduler, $this->local_feed_configs );
		$feed_generator->handle_end_action( [] );

		$this->assertFalse( get_transient( TrackerSnapshot::TRANSIENT_WCTRACKER_FEED_GENERATION_WALL_TIME ) );
	}
}
