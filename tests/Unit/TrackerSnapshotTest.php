<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\ProductFeedStatus;
use Automattic\WooCommerce\Pinterest\TrackerSnapshot;
use Pinterest_For_Woocommerce;

class TrackerSnapshotTest extends \WP_UnitTestCase {

	public static $default_settings = array(
		'version'                => PINTEREST_FOR_WOOCOMMERCE_VERSION,
		'track_conversions'      => true,
		'enhanced_match_support' => true,
		'save_to_pinterest'      => true,
		'rich_pins_on_posts'     => true,
		'rich_pins_on_products'  => true,
		'product_sync_enabled'   => true,
		'enable_debug_logging'   => false,
		'erase_plugin_data'      => false,
	);

	function setUp() {
		parent::setUp();
		update_option( 'woocommerce_allow_tracking', 'yes' );
	}

	function test_settings_are_tracked_by_woo_tracker_if_opt_in() {
		Pinterest_For_Woocommerce::save_settings( self::$default_settings );

		TrackerSnapshot::maybe_init();
		$tracks = apply_filters( 'woocommerce_tracker_data', [] );

		$this->assertEquals( 'yes', $tracks['extensions'][PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX]['settings']['track_conversions'], "Boolean track value 'true' is tracked as 'yes'" );
		$this->assertEquals( 'no', $tracks['extensions'][PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX]['settings']['erase_plugin_data'], "Boolean track value 'false' is tracked as 'no'" );
		$this->assertEquals( count( $tracks['extensions'][PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX]['settings'] ), count(  self::$default_settings ), "All the values should be tracked" );
	}

	public function test_extension_connection_status_is_tracked_as_no_if_no_feed_registered() {
		Pinterest_For_Woocommerce::save_settings( self::$default_settings );

		TrackerSnapshot::maybe_init();
		$tracks = apply_filters( 'woocommerce_tracker_data', [] );

		$this->assertEquals( 'no', $tracks['extensions'][PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX]['store']['connected'] );
		$this->assertEquals( 'no', $tracks['extensions'][PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX]['store']['actively_syncing'] );
	}

	public function test_extension_connection_status_is_tracked_as_no_if_feed_registered_has_empty_value() {
		Pinterest_For_Woocommerce::save_settings( self::$default_settings );
		Pinterest_For_Woocommerce::save_data( 'feed_registered', '' );

		TrackerSnapshot::maybe_init();
		$tracks = apply_filters( 'woocommerce_tracker_data', [] );

		$this->assertEquals( 'no', $tracks['extensions'][PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX]['store']['connected'] );
		$this->assertEquals( 'no', $tracks['extensions'][PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX]['store']['actively_syncing'] );
	}

	public function test_extension_connection_status_is_tracked_as_yes_if_opt_in() {
		Pinterest_For_Woocommerce::save_settings( self::$default_settings );
		Pinterest_For_Woocommerce::save_token(
			array(
				'access_token' => 'some-fake-access-token',
			)
		);
		Pinterest_For_Woocommerce::save_data( 'feed_registered', uniqid() );

		TrackerSnapshot::maybe_init();
		$tracks = apply_filters( 'woocommerce_tracker_data', [] );

		$this->assertEquals( 'yes', $tracks['extensions'][PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX]['store']['connected'] );
		$this->assertEquals( 'yes', $tracks['extensions'][PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX]['store']['actively_syncing'] );
	}

	public function test_extension_feed_status_is_tracked_if_opt_in() {
		Pinterest_For_Woocommerce::save_settings( self::$default_settings );

		TrackerSnapshot::maybe_init();
		$tracks = apply_filters( 'woocommerce_tracker_data', [] );

		$this->assertArrayHasKey( 'feed', $tracks['extensions'][PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX] );
		$this->assertArrayHasKey( 'generation_time', $tracks['extensions'][PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX]['feed'] );
		$this->assertArrayHasKey( 'product_count', $tracks['extensions'][PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX]['feed'] );
	}

	public function test_extension_feed_generation_time_and_recent_product_count_have_values_from_product_feed_status_storage() {
		Pinterest_For_Woocommerce::save_settings( self::$default_settings );

		TrackerSnapshot::maybe_init();

		ProductFeedStatus::set(
			array(
				'product_count'                                              => 19,
				ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME            => 786453786,
				ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT => 99,
			)
		);
		$tracks = apply_filters( 'woocommerce_tracker_data', [] );
		$this->assertEquals( 786453786, $tracks['extensions'][PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX]['feed']['generation_time'] );
		$this->assertEquals( 99, $tracks['extensions'][PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX]['feed']['product_count'] );

		ProductFeedStatus::set(
			array(
				'product_count'                                              => 39,
				ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME            => -87935467089345,
				ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT => 69,
			)
		);
		$tracks = apply_filters( 'woocommerce_tracker_data', [] );
		$this->assertEquals( -87935467089345, $tracks['extensions'][PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX]['feed']['generation_time'] );
		$this->assertEquals( 69, $tracks['extensions'][PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX]['feed']['product_count'] );
	}

	function test_settings_are_not_tracked_by_woo_tracker_if_opt_out() {
		update_option( 'woocommerce_allow_tracking', 'no' );

		Pinterest_For_Woocommerce::save_settings( self::$default_settings );

		TrackerSnapshot::maybe_init();
		$tracks = apply_filters( 'woocommerce_tracker_data', [] );

		$this->assertTrue( count($tracks) === 0, "Track data should be empty whe OPT-OUT" );
	}
}
