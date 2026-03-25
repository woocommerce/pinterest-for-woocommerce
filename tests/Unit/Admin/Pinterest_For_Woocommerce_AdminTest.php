<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Admin;

require_once dirname( __DIR__, 3 ) . '/includes/admin/class-pinterest-for-woocommerce-admin.php';

use Pinterest_For_Woocommerce_Admin;
use WP_UnitTestCase;

/**
 * Class Pinterest_For_Woocommerce_AdminTest.
 */
class Pinterest_For_Woocommerce_AdminTest extends WP_UnitTestCase {

	/**
	 * @var Pinterest_For_Woocommerce_Admin
	 */
	private Pinterest_For_Woocommerce_Admin $admin;

	/**
	 * {@inheritdoc}
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->admin = new Pinterest_For_Woocommerce_Admin();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function tearDown(): void {
		parent::tearDown();
		remove_all_filters( 'plugin_action_links_' . PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME );
		unset( $this->admin );
	}

	/**
	 * Test that the plugin action links filter is registered.
	 *
	 * @return void
	 */
	public function test_plugin_action_links_filter_is_registered(): void {
		$this->assertNotFalse(
			has_filter( 'plugin_action_links_' . PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME ),
			'plugin_action_links filter should be registered'
		);
	}

	/**
	 * Test that a Settings link pointing to the Pinterest landing page is prepended.
	 *
	 * @return void
	 */
	public function test_plugin_action_links_links_to_landing(): void {
		$result = $this->admin->plugin_action_links( array() );

		$this->assertCount( 1, $result, 'Should have exactly one link added' );
		$this->assertStringContainsString( 'path=/pinterest/landing', $result[0], 'Link should point to the landing page' );
		$this->assertStringContainsString( 'page=wc-admin', $result[0], 'Link should target the wc-admin page' );
		$this->assertStringContainsString( 'Settings', $result[0], 'Link text should be Settings' );
	}

	/**
	 * Test that the Settings link is prepended before existing action links.
	 *
	 * @return void
	 */
	public function test_plugin_action_links_prepends_to_existing_links(): void {
		$existing_links = array( '<a href="#">Deactivate</a>' );

		$result = $this->admin->plugin_action_links( $existing_links );

		$this->assertCount( 2, $result, 'Should have Settings + original link' );
		$this->assertStringContainsString( 'Settings', $result[0], 'Settings link should be first' );
		$this->assertStringContainsString( 'Deactivate', $result[1], 'Existing links should be preserved' );
	}
}
