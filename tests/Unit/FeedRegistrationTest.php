<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\FeedFileOperations;
use Automattic\WooCommerce\Pinterest\FeedRegistration;
use Automattic\WooCommerce\Pinterest\LocalFeedConfigs;
use Automattic\WooCommerce\Pinterest\UnauthorizedAccessMonitor;
use WP_UnitTestCase;

class FeedRegistrationTest extends WP_UnitTestCase {

	/** @var FeedRegistration */
	private $feed_registration;

	public function setUp() {
		parent::setUp();
		$local_feed_configs   = $this->createMock( LocalFeedConfigs::class );
		$feed_file_operations = $this->createMock( FeedFileOperations::class );
		$local_feed_configs
			->method( 'get_configurations' )
			->willReturn( array() );

		$this->feed_registration = new FeedRegistration( $local_feed_configs, $feed_file_operations );
	}

	public function test_init_does_not_add_action_scheduler_action_if_action_is_on_pause() {
		UnauthorizedAccessMonitor::pause_as_tasks();

		$this->feed_registration->init();

		$has_action = has_action(
			'pinterest-for-woocommerce-handle-feed-registration',
			array( $this->feed_registration, 'handle_feed_registration' )
		);
		$this->assertFalse( $has_action );

		$has_action = as_has_scheduled_action( 'pinterest-for-woocommerce-handle-feed-registration' );
		$this->assertFalse( $has_action );
	}

	public function test_init_adds_as_recurring_action() {
		$this->feed_registration->init();

		$this->assertEquals(
			10,
			has_action(
				'pinterest-for-woocommerce-handle-feed-registration',
				array( $this->feed_registration, 'handle_feed_registration' )
			)
		);

		$has_action = as_has_scheduled_action( 'pinterest-for-woocommerce-handle-feed-registration' );
		$this->assertTrue( $has_action );
	}
}
