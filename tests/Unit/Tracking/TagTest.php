<?php

namespace Automattic\WooCommerce\Pinterest\Tracking;

use Pinterest_For_Woocommerce;

class TagTest extends \WP_UnitTestCase {

	function setUp(): void {
		parent::setUp();
	}

	public function test_adds_hooks() {
		$tag = new Tag();

		$this->assertEquals( 10, has_action( 'wp_head', array( $tag, 'print_script' ) ) );
		$this->assertEquals( 0, has_action( 'wp_body_open', array( $tag, 'print_noscript' ) ) );
		$this->assertEquals( 10, has_action( 'shutdown', array( $tag, 'save_deferred_events' ) ) );
	}

	public function test_print_script_prints_tag() {
		Pinterest_For_Woocommerce::save_settings( array( 'tracking_tag' => 'YU9AOV86F', 'enhanced_match_support' => false ) );

		$tag = new Tag();

		ob_start();
		$tag->print_script();
		$script = ob_get_contents();
		ob_end_clean();

		$expected = "<!-- Pinterest Pixel Base Code -->\n<script type=\"text/javascript\">\n  !function(e){if(!window.pintrk){window.pintrk=function(){window.pintrk.queue.push(Array.prototype.slice.call(arguments))};var n=window.pintrk;n.queue=[],n.version=\"3.0\";var t=document.createElement(\"script\");t.async=!0,t.src=e;var r=document.getElementsByTagName(\"script\")[0];r.parentNode.insertBefore(t,r)}}(\"https://s.pinimg.com/ct/core.js\");\n\n  pintrk('load', 'yu9aov86f', { np: \"woocommerce\" } );\n  pintrk('page');\n</script>\n<!-- End Pinterest Pixel Base Code -->\n<script id=\"pinterest-tag-placeholder\"></script>";
		$this->assertEquals( $expected, $script );
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

		$expected = "<!-- Pinterest Pixel Base Code -->\n<script type=\"text/javascript\">\n  !function(e){if(!window.pintrk){window.pintrk=function(){window.pintrk.queue.push(Array.prototype.slice.call(arguments))};var n=window.pintrk;n.queue=[],n.version=\"3.0\";var t=document.createElement(\"script\");t.async=!0,t.src=e;var r=document.getElementsByTagName(\"script\")[0];r.parentNode.insertBefore(t,r)}}(\"https://s.pinimg.com/ct/core.js\");\n\n pintrk('load', 'ju9rag86q', { em: '122dc8b4cb47fa7179db75f0c04b28dd', np: \"woocommerce\" });\n  pintrk('page');\n</script>\n<!-- End Pinterest Pixel Base Code -->\n<script id=\"pinterest-tag-placeholder\"></script>";
		$this->assertEquals( $expected, $script );
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

		Tag::add_deferred_event( 'some_event_name_13512345', array( 'data' => 'James B0nd' ) );
		Tag::save_deferred_events();

		$events = Tag::load_deferred_events();

		$expected = array(
			"pintrk( 'track', 'some_event_name_13512345' , {\"data\":\"James B0nd\"});"
		);
		$this->assertEquals( $expected, $events );
	}
}
