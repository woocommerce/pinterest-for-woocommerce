<?php
/**
 * Plugin requirement tests.
 *
 * @package Pinterest/Tests
 */

namespace Automattic\WooCommerce\Pinterest;

use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

/**
 * Tests the minimum WordPress version at the runtime gate.
 */
class PluginRequirementsTest extends WP_UnitTestCase {

	/**
	 * Checks the supported WordPress version boundary.
	 *
	 * @dataProvider wordpress_version_provider
	 * @param string $version WordPress version to check.
	 * @param bool   $expected Whether the version is supported.
	 */
	public function test_wordpress_version_requirement( $version, $expected ) {
		global $wp_version;

		$original_version = $wp_version;
		$wp_version       = $version; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Exercise the runtime version check.

		try {
			$this->assertSame( $expected, Pinterest_For_Woocommerce::instance()->check_plugin_requirements() );
		} finally {
			$wp_version = $original_version; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the test environment.
		}
	}

	/**
	 * Provides WordPress versions at the supported boundary.
	 *
	 * @return array
	 */
	public function wordpress_version_provider() {
		return array(
			'below minimum' => array( '6.8.99', false ),
			'minimum'       => array( '6.9', true ),
		);
	}
}
