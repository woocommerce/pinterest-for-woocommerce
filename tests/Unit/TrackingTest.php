<?php

namespace Automattic\WooCommerce\Pinterest;

use Pinterest_For_Woocommerce;

class TrackingTest extends \WP_UnitTestCase {

	function setUp() {
		parent::setUp();
	}

	public function test_init_tracking_inits() {
		Pinterest_For_Woocommerce::save_settings( array( 'track_conversions' => true ) );
		add_filter( 'wp_doing_cron', '__return_false' );

		$tracking = Pinterest_For_Woocommerce::init_tracking();

		$this->assertEquals( 10, has_action( 'wp_footer', array( $tracking, 'handle_page_visit' ) ) );
		$this->assertEquals( 10, has_action( 'wp_footer', array( $tracking, 'handle_view_category' ) ) );
		$this->assertEquals( 10, has_action( 'woocommerce_add_to_cart', array( $tracking, 'handle_add_to_cart' ) ) );
		$this->assertEquals( 10, has_action( 'woocommerce_checkout_order_created', array( $tracking, 'handle_checkout' ) ) );
		$this->assertEquals( 10, has_action( 'wp_footer', array( $tracking, 'handle_search' ) ) );
	}

	function test_tracking_adds_actions_monitoring() {
		$tracking = new Tracking();

		$this->assertEquals( 10, has_action( 'wp_footer', array( $tracking, 'handle_page_visit' ) ) );
		$this->assertEquals( 10, has_action( 'wp_footer', array( $tracking, 'handle_view_category' ) ) );
		$this->assertEquals( 10, has_action( 'woocommerce_add_to_cart', array( $tracking, 'handle_add_to_cart' ) ) );
		$this->assertEquals( 10, has_action( 'woocommerce_checkout_order_created', array( $tracking, 'handle_checkout' ) ) );
		$this->assertEquals( 10, has_action( 'wp_footer', array( $tracking, 'handle_search' ) ) );
	}

	public function test_tracker_is_added() {

	}
}
