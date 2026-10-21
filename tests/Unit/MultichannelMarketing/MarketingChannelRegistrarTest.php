<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\MultichannelMarketing;

use Automattic\WooCommerce\Admin\Marketing\MarketingChannels;
use Automattic\WooCommerce\Pinterest\MultichannelMarketing\MarketingChannelRegistrar;
use Automattic\WooCommerce\Pinterest\MultichannelMarketing\PinterestChannel;
use WP_UnitTestCase;

/**
 * Tests that Pinterest registers itself as a WooCommerce marketing channel.
 *
 * @version 1.6.0
 */
class MarketingChannelRegistrarTest extends WP_UnitTestCase {

	/**
	 * Start from a fresh MarketingChannels instance so registrations don't leak between tests.
	 */
	public function setUp(): void {
		parent::setUp();
		wc_get_container()->reset_all_resolved();
	}

	/**
	 * Drop the channel registered by the test.
	 */
	public function tearDown(): void {
		wc_get_container()->reset_all_resolved();
		parent::tearDown();
	}

	/**
	 * The plugin hooks the registrar without relying on the removed WC_MCM_EXISTS constant.
	 */
	public function test_registrar_is_hooked_on_init() {
		$this->assertFalse( defined( 'WC_MCM_EXISTS' ) );
		$this->assertSame( 10, has_action( 'init', array( MarketingChannelRegistrar::class, 'register' ) ) );
	}

	/**
	 * The hooked callback registers Pinterest with WooCommerce's marketing channels.
	 */
	public function test_register_adds_pinterest_to_marketing_channels() {
		MarketingChannelRegistrar::register();

		$channels = wc_get_container()->get( MarketingChannels::class )->get_registered_channels();

		$this->assertCount( 1, $channels );
		$this->assertSame( PinterestChannel::get_instance(), $channels[0] );
		$this->assertSame( 'pinterest-for-woocommerce', $channels[0]->get_slug() );
	}
}
