<?php
/**
 * Plugin requirement tests.
 *
 * @package Pinterest/Tests
 */

namespace Automattic\WooCommerce\Pinterest;

use Pinterest_For_Woocommerce;
use PHPUnit\Util\PHP\AbstractPhpProcess;
use WP_UnitTestCase;

/**
 * Tests the minimum WordPress and WooCommerce versions at the runtime gate.
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

	/**
	 * Checks the supported WooCommerce version boundary.
	 *
	 * @dataProvider woocommerce_version_provider
	 * @param string $version WooCommerce version to check.
	 * @param bool   $expected Whether the version is supported.
	 */
	public function test_woocommerce_version_requirement( $version, $expected ) {
		// A fresh process can define WC_VERSION without the bootstrap's immutable constant.
		$script = sprintf(
			<<<'PHP'
			<?php
			define( 'ABSPATH', %s );
			define( 'WC_VERSION', %s );
			define( 'PINTEREST_FOR_WOOCOMMERCE_VERSION', %s );
			$wp_version = '6.9';
			$plugin_dir = %s;
			function esc_html__( $text, $domain ) { return $text; }
			function apply_filters( $hook, $value ) { return $value; }
			function as_has_scheduled_action() {}
			function is_admin() { return false; }
			require $plugin_dir . '/src/Utilities/Tracks.php';
			require $plugin_dir . '/class-pinterest-for-woocommerce.php';
			echo json_encode( ( new Pinterest_For_Woocommerce() )->check_plugin_requirements() );
			PHP,
			// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Encode values as PHP literals for the isolated process.
			var_export( dirname( __DIR__, 2 ) . '/', true ),
			var_export( $version, true ),
			var_export( PINTEREST_FOR_WOOCOMMERCE_VERSION, true ),
			var_export( dirname( __DIR__, 2 ), true )
			// phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_var_export
		);

		$result = AbstractPhpProcess::factory()->runJob( $script );
		$this->assertSame( '', $result['stderr'] );
		$this->assertSame( $expected, json_decode( $result['stdout'] ) );
	}

	/**
	 * Provides WooCommerce versions at the supported boundary.
	 *
	 * @return array
	 */
	public function woocommerce_version_provider() {
		return array(
			'below minimum' => array( '10.8.99', false ),
			'minimum'       => array( '10.9.0', true ),
		);
	}
}
