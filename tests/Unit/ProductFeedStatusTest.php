<?php

namespace Automattic\WooCommerce\Pinterest;

use Pinterest_For_Woocommerce;

/**
 * Tests the ProductFeedStatus class.
 *
 * @version x.x.x
 */
class ProductFeedStatusTest extends \WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		ProductFeedStatus::deregister();
	}

	public function test_deregister_resets_feed_generation_product_feed_status_properties_to_defaults() {
		ProductFeedStatus::set(
			array(
				ProductFeedStatus::PROP_FEED_GENERATION_WALL_START_TIME      => 11214,
				ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME            => 53452,
				ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT => 43623,
			)
		);

		ProductFeedStatus::deregister();

		$start_time    = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_START_TIME ];
		$wall_time     = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];
		$product_count = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT ];

		$this->assertFalse( $start_time );
		$this->assertEquals( 0, $wall_time );
		$this->assertEquals( 0, $product_count );
	}

	/**
	 * Public cleanup entry points must remove telemetry from persistent storage.
	 *
	 * @return array
	 */
	public function data_persistent_cleanup() {
		return array(
			'deregister'                          => array( array( ProductFeedStatus::class, 'deregister' ) ),
			'authentication reset'                => array( array( Pinterest_For_Woocommerce::class, 'reset_connection' ) ),
			'disconnect without business account' => array( array( Pinterest_For_Woocommerce::class, 'disconnect' ) ),
		);
	}

	/**
	 * Completed/error telemetry must not return on the next request after cleanup.
	 *
	 * @param callable $cleanup Native cleanup entry point.
	 * @dataProvider data_persistent_cleanup
	 */
	public function test_cleanup_removes_persisted_feed_status_and_reloads_defaults( $cleanup ) {
		$expected = array(
			'status'                               => 'pending_config',
			'last_activity'                        => 0,
			'product_count'                        => 0,
			'error_message'                        => '',
			'feed_generation_wall_start_time'      => false,
			'feed_generation_wall_time'            => 0,
			'feed_generation_recent_product_count' => 0,
		);
		$state    = new \ReflectionProperty( ProductFeedStatus::class, 'state' );
		$state->setAccessible( true );
		$previous = $state->getValue();
		$state->setValue( null, array() );
		ksort( $expected );
		$read_status = function () {
			$current = ProductFeedStatus::get();
			ksort( $current );
			return $current;
		};
		Pinterest_For_Woocommerce::set_default_settings();
		ProductFeedStatus::set(
			array(
				'status'                               => 'generated',
				'product_count'                        => 42,
				'error_message'                        => 'Previous failure.',
				'feed_generation_wall_start_time'      => 123,
				'feed_generation_wall_time'            => 12,
				'feed_generation_recent_product_count' => 42,
			)
		);
		$prefix = ProductFeedStatus::PINTEREST_FOR_WOOCOMMERCE_FEEDS_DATA_PREFIX;
		update_option( $prefix . 'unrelated_fixture', 'Retained' );
		foreach ( array_keys( $expected ) as $key ) {
			set_transient( $prefix . $key, 'Legacy telemetry', HOUR_IN_SECONDS );
		}
		$this->assertSame( 'generated', get_option( $prefix . 'status' ) );
		$this->assertSame( 42, get_option( $prefix . 'product_count' ) );
		try {
			call_user_func( $cleanup );
			foreach ( array_keys( $expected ) as $key ) {
				$this->assertSame( 'missing', get_option( $prefix . $key, 'missing' ), $key );
				$this->assertFalse( get_transient( $prefix . $key ), $key );
			}
			$this->assertSame( 'Retained', get_option( $prefix . 'unrelated_fixture' ) );
			$this->assertSame( $expected, $read_status() );
			$state->setValue( null, array() );
			$this->assertSame( $expected, $read_status(), 'A fresh request must not revive completed telemetry.' );
			call_user_func( $cleanup );
			$state->setValue( null, array() );
			$this->assertSame( $expected, $read_status(), 'Repeated cleanup stays empty.' );
		} finally {
			$state->setValue( null, $previous );
		}
	}

	public function test_product_feed_state_has_feed_related_data_entries() {
		$this->assertArrayHasKey( ProductFeedStatus::PROP_FEED_GENERATION_WALL_START_TIME, ProductFeedStatus::STATE_PROPS );
		$this->assertArrayHasKey( ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME, ProductFeedStatus::STATE_PROPS );
		$this->assertArrayHasKey( ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT, ProductFeedStatus::STATE_PROPS );
	}

	public function test_product_feed_state_remembers_feed_generation_data() {
		ProductFeedStatus::set(
			array(
				ProductFeedStatus::PROP_FEED_GENERATION_WALL_START_TIME      => 4863486,
				ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME            => 6464624,
				ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT => 63,
			)
		);

		$feed_generation_wall_start_time = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_START_TIME ];
		$feed_generation_wall_time       = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];
		$feed_generation_product_count   = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT ];

		$this->assertEquals( 4863486, $feed_generation_wall_start_time );
		$this->assertEquals( 6464624, $feed_generation_wall_time );
		$this->assertEquals( 63, $feed_generation_product_count );
	}

	public function test_product_feed_state_returns_defaults_for_feed_generation_data() {
		$feed_generation_wall_start_time      = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_START_TIME ];
		$feed_generation_wall_time            = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];
		$feed_generation_recent_product_count = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT ];

		$this->assertFalse( $feed_generation_wall_start_time );
		$this->assertEquals( 0, $feed_generation_wall_time );
		$this->assertEquals( 0, $feed_generation_recent_product_count );
	}

	public function test_reset_feed_file_generation_time_resets_feed_generation_data_to_defaults() {
		$time_test_started = time();
		ProductFeedStatus::set(
			array(
				ProductFeedStatus::PROP_FEED_GENERATION_WALL_START_TIME      => 373111,
				ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME            => 511511,
				ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT => 214,
			)
		);

		ProductFeedStatus::reset_feed_file_generation_time();

		$feed_generation_wall_start_time      = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_START_TIME ];
		$feed_generation_wall_time            = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];
		$feed_generation_recent_product_count = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT ];

		$this->assertGreaterThanOrEqual( $time_test_started, $feed_generation_wall_start_time );
		$this->assertEquals( 511511, $feed_generation_wall_time );
		$this->assertEquals( 214, $feed_generation_recent_product_count );
	}

	public function test_set_feed_file_generation_time_sets_time_it_took_to_generate_the_feed() {
		ProductFeedStatus::set(
			array(
				ProductFeedStatus::PROP_FEED_GENERATION_WALL_START_TIME => 10,
			)
		);

		ProductFeedStatus::set_feed_file_generation_time( 12 );

		$feed_generation_wall_time = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];

		$this->assertEquals( 2, $feed_generation_wall_time );
	}

	public function test_mark_feed_file_generation_as_failed_sets_generation_wall_time_to_negative_value() {
		ProductFeedStatus::mark_feed_file_generation_as_failed();

		$feed_generation_wall_time = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];

		$this->assertEquals( -1, $feed_generation_wall_time );
	}
}
