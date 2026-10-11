<?php

namespace Automattic\WooCommerce\Pinterest\Tracking;

use Pinterest_For_Woocommerce;

/**
 * Tests the Tag tracker class.
 *
 * @version x.x.x
 */
class TagTest extends \WP_UnitTestCase {

	public function test_adds_hooks() {
		$tag = new Tag();
		$tag->init_hooks();

		$this->assertEquals( 10, has_action( 'wp_footer', array( $tag, 'print_script' ) ) );
		$this->assertEquals( 10, has_action( 'wp_footer', array( $tag, 'print_noscript' ) ) );
		$this->assertEquals( 10, has_action( 'shutdown', array( $tag, 'save_deferred_events' ) ) );
	}

	public function test_disables_hooks() {
		$tag = new Tag();
		$tag->init_hooks();

		$tag->disable_hooks();

		$this->assertFalse( has_action( 'wp_footer', array( $tag, 'print_script' ) ) );
		$this->assertFalse( has_action( 'wp_footer', array( $tag, 'print_noscript' ) ) );
		$this->assertFalse( has_action( 'shutdown', array( $tag, 'save_deferred_events' ) ) );
	}

	/** WordPress script attributes apply without changing base/event/placeholder order. */
	public function test_tracking_scripts_use_wordpress_attributes() {
		Pinterest_For_Woocommerce::save_settings( array( 'tracking_tag' => 'inline-test' ) );
		$events = new \ReflectionProperty( Tag::class, 'events' );
		$events->setAccessible( true );
		$saved_events = $events->getValue();
		$attributes   = static function ( $attributes ) {
			$attributes['nonce'] = 'pinterest-inline-test';
			return $attributes;
		};
		add_filter( 'wp_inline_script_attributes', $attributes );
		$events->setValue( null, array() );
		Tag::add_event( 'test-event', array( 'event_id' => 'unchanged-id' ) );
		ob_start();
		( new Tag() )->print_script();
		$output = ob_get_clean();
		$events->setValue( null, $saved_events );
		$this->assertSame( 3, substr_count( $output, 'nonce="pinterest-inline-test"' ) );
		$this->assertLessThan( strpos( $output, "pintrk( 'track'" ), strpos( $output, "pintrk('load'" ) );
		$this->assertLessThan( strpos( $output, 'pinterest-tag-placeholder' ), strpos( $output, "pintrk( 'track'" ) );
		$this->assertStringContainsString( '"event_id":"unchanged-id"', $output );
	}

	/** AJAX cart fragments retain the public selector and use WordPress script attributes. */
	public function test_cart_fragment_uses_wordpress_script_attributes() {
		$attributes = static function ( $attributes ) {
			$attributes['nonce'] = 'pinterest-cart-test';
			return $attributes;
		};
		$is_ajax    = static function () {
			return true;
		};
		add_filter( 'wp_inline_script_attributes', $attributes );
		add_filter( 'wp_doing_ajax', $is_ajax );
		$tag  = new Tag();
		$data = new Data\Product( 'cart-event', 123, 'Test product', '', '', 10, 'USD', 2 );
		$tag->track_event( \Automattic\WooCommerce\Pinterest\Tracking::EVENT_ADD_TO_CART, $data );
		/**
		 * Applies the native cart fragment hooks for an AJAX response.
		 *
		 * @since x.x.x
		 */
		$fragments = apply_filters( 'woocommerce_add_to_cart_fragments', array() );
		$script    = $fragments['script#pinterest-tag-placeholder'];
		$this->assertStringContainsString( 'id="pinterest-tag-placeholder"', $script );
		$this->assertStringContainsString( 'nonce="pinterest-cart-test"', $script );
		$this->assertStringContainsString( "pintrk( 'track', 'AddToCart'", $script );
		$this->assertStringContainsString( '"event_id":"cart-event"', $script );
		$this->assertStringContainsString( '"value":20', $script );
	}

	public function test_print_script_prints_tag() {
		Pinterest_For_Woocommerce::save_settings( array( 'tracking_tag' => 'YU9AOV86F', 'enhanced_match_support' => false ) );
		wp_set_current_user( $this->factory->user->create() );

		$tag = new Tag();

		ob_start();
		$tag->print_script();
		$script = ob_get_contents();
		ob_end_clean();

		$this->assert_tag_script( $script, 'yu9aov86f', '{"np":"woocommerce"}' );
	}

	public function test_print_script_prints_tag_with_enhanced_match_support() {
		Pinterest_For_Woocommerce::save_settings( array( 'tracking_tag' => 'JU9RAG86Q', 'enhanced_match_support' => true ) );

		$user_id = $this->factory->user->create( array( 'user_email' => 'address@somesite.com' ) );
		wp_set_current_user( $user_id );

		$tag = new Tag();

		ob_start();
		$tag->print_script();
		$script = ob_get_contents();
		ob_end_clean();

		$expected_user_data = wp_json_encode(
			array(
				'np'          => 'woocommerce',
				'em'          => '122dc8b4cb47fa7179db75f0c04b28dd',
				'external_id' => hash( 'sha256', (string) $user_id ),
			)
		);
		$this->assert_tag_script( $script, 'ju9rag86q', $expected_user_data );
	}

	/**
	 * Compare the pixel output using the current WordPress script-tag format.
	 *
	 * @param string $script Printed HTML.
	 * @param string $tag_id Pinterest tag ID.
	 * @param string $user_data Encoded user data.
	 */
	private function assert_tag_script( $script, $tag_id, $user_data ) {
		$base_code = "  !function(e){if(!window.pintrk){window.pintrk=function(){window.pintrk.queue.push(Array.prototype.slice.call(arguments))};var n=window.pintrk;n.queue=[],n.version=\"3.0\";var t=document.createElement(\"script\");t.async=!0,t.src=e;var r=document.getElementsByTagName(\"script\")[0];r.parentNode.insertBefore(t,r)}}(\"https://s.pinimg.com/ct/core.js\");\n\n  pintrk('load', '{$tag_id}', {$user_data} );\n  pintrk('page');";
		$expected  = "<!-- Pinterest Pixel Base Code -->\n";
		$expected .= wp_get_inline_script_tag( $base_code, array( 'type' => 'text/javascript' ) );
		$expected .= "<!-- End Pinterest Pixel Base Code -->\n";
		$expected .= wp_get_inline_script_tag( '', array( 'id' => 'pinterest-tag-placeholder' ) );
		$this->assertSame( $expected, $script );
	}

	public function test_print_noscript() {
		Pinterest_For_Woocommerce::save_settings( array( 'tracking_tag' => 'VR5R2GDTE' ) );

		$tag = new Tag();

		ob_start();
		$tag->print_noscript();
		$noscript = ob_get_contents();
		ob_end_clean();

		$expected = '<!-- Pinterest Pixel Base Code --><noscript><img height="1" width="1" style="display:none;" alt="" src="https://ct.pinterest.com/v3/?tid=vr5r2gdte&noscript=1" /></noscript><!-- End Pinterest Pixel Base Code -->';
		$this->assertEquals( $expected, $noscript );
	}

	public function test_load_deferred_events_returns_empty_array_after_init() {
		$events = Tag::load_deferred_events();

		$this->assertEmpty( $events );
	}

	public function test_load_deferred_events_return_saved_events() {
		$user_id = $this->factory->user->create( array( 'user_email' => 'address@somesite.com' ) );
		wp_set_current_user( $user_id );

		Tag::add_deferred_event( 'some_event_name_13512345', array( 'data' => 'James Bond' ) );
		Tag::save_deferred_events();

		$events = Tag::load_deferred_events();

		$expected = array(
			"pintrk( 'track', 'some_event_name_13512345' , {\"data\":\"James Bond\"});",
		);
		$this->assertEquals( $expected, $events );
	}
}
