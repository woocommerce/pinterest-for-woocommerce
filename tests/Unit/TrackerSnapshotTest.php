<?php

namespace Automattic\WooCommerce\Pinterest;

class TrackerSnapshotTest extends \WP_UnitTestCase {


	public static $default_settings = array(
		'version'                          => PINTEREST_FOR_WOOCOMMERCE_VERSION,
		'track_conversions'                => true,
		'enhanced_match_support'           => true,
		'automatic_enhanced_match_support' => true,
		'save_to_pinterest'                => true,
		'rich_pins_on_posts'               => true,
		'rich_pins_on_products'            => true,
		'product_sync_enabled'             => true,
		'enable_debug_logging'             => false,
		'erase_plugin_data'                => false,
	);

	function setUp() {
		parent::setUp();
		update_option( 'woocommerce_allow_tracking', 'yes' );
	}


	function test_settings_are_tracked_by_woo_tracker_if_opt_in() {

		\Pinterest_For_Woocommerce::save_settings( self::$default_settings );

		TrackerSnapshot::maybe_init();
		$tracks = apply_filters( 'woocommerce_tracker_data', [] );


		$this->assertEquals( $tracks['extensions'][PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX]['settings']['track_conversions'], 'yes', "Boolean track value 'true' is tracked as 'yes'" );
		$this->assertEquals( $tracks['extensions'][PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX]['settings']['erase_plugin_data'], 'no', "Boolean track value 'false' is tracked as 'no'" );
		$this->assertEquals( count( $tracks['extensions'][PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX]['settings'] ), count(  self::$default_settings ), "All the values should be tracked" );
	}

	function test_settings_are_not_tracked_by_woo_tracker_if_opt_out() {

		update_option( 'woocommerce_allow_tracking', 'no' );

		\Pinterest_For_Woocommerce::save_settings( self::$default_settings );

		TrackerSnapshot::maybe_init();
		$tracks = apply_filters( 'woocommerce_tracker_data', [] );

		$this->assertTrue( count($tracks) === 0, "Track data should be empty whe OPT-OUT" );

	}

}
