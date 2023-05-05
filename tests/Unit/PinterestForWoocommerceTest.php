<?php

use Automattic\WooCommerce\Pinterest\RefreshToken;

class PinterestForWoocommerceTest extends \WP_UnitTestCase {

	/**
	 * Test of the plugin has refresh token action initialised.
	 *
	 * @return void
	 */
	public function test_pinterest_api_access_token_scheduled_for_refresh() {
		Pinterest_For_Woocommerce();
		$this->assertEquals( 10, has_action( 'init', [ RefreshToken::class, 'schedule_event' ] ) );
	}
}
