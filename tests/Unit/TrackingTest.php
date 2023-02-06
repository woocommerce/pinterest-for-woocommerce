<?php

namespace Automattic\WooCommerce\Pinterest;

class TrackingTest extends \WP_UnitTestCase {

	function setUp() {
		parent::setUp();
		update_option( 'woocommerce_allow_tracking', 'yes' );
	}

	function test_ajax_tracking_snippet_action_added() {
		add_option( 'woocommerce_enable_ajax_add_to_cart', 'yes' );
		add_option( 'woocommerce_cart_redirect_after_add', 'no' );
		\Pinterest_For_Woocommerce::save_setting( 'track_conversions', true );
		\Pinterest_For_Woocommerce::save_setting( 'tracking_tag', 'some-tag-id' );

		Tracking::maybe_init();

		$this->assertEquals(
			20,
			has_action( 'wp_enqueue_scripts', array( Tracking::class, 'ajax_tracking_snippet' ) )
		);
		$this->assertEquals(
			10,
			has_filter( 'woocommerce_loop_add_to_cart_args', array( Tracking::class, 'filter_add_to_cart_attributes' ) )
		);
	}
}
