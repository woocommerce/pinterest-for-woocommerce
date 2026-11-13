<?php
/**
 * Plugin requirement tests.
 *
 * @package Pinterest/Tests
 */

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Grow\Tools\CompatChecker\v0_0_1\Checker;
use Automattic\WooCommerce\Grow\Tools\CompatChecker\v0_0_1\Checks\WCCompatibility;
use Automattic\WooCommerce\Grow\Tools\CompatChecker\v0_0_1\Checks\WPCompatibility;
use Pinterest_For_Woocommerce;
use PHPUnit\Util\PHP\AbstractPhpProcess;
use WC_Admin_Notices;
use WP_UnitTestCase;

/**
 * Tests plugin requirements and compatibility notices.
 *
 * @version x.x.x
 */
class PluginRequirementsTest extends WP_UnitTestCase {

	/** @var string[] Notices present before the test. */
	private $original_notices;

	/** Capture notice state and avoid remote version lookups. */
	public function setUp(): void {
		parent::setUp();
		$this->original_notices = WC_Admin_Notices::get_notices();
		set_transient( 'compat_checker_wc_versions', array( WC_VERSION ) );
	}

	/** Restore WooCommerce's static notice cache and the current screen. */
	public function tearDown(): void {
		foreach ( array_diff( WC_Admin_Notices::get_notices(), $this->original_notices ) as $notice ) {
			WC_Admin_Notices::remove_notice( $notice );
		}
		foreach ( $this->original_notices as $notice ) {
			WC_Admin_Notices::add_notice( $notice );
		}
		set_current_screen( 'front' );
		parent::tearDown();
	}

	/**
	 * Newer WooCommerce versions must not produce Pinterest's untested warning.
	 */
	public function test_untested_woocommerce_notice_is_suppressed() {
		set_current_screen( 'plugins' );
		$data             = Checker::instance()->get_plugin_data( PINTEREST_FOR_WOOCOMMERCE_PLUGIN_FILE, PINTEREST_FOR_WOOCOMMERCE_VERSION );
		$data['TestedWC'] = '1.0';
		$check            = new WCCompatibility();

		$this->assertTrue( $check->is_compatible( $data ) );
		$this->assertSame( 10, has_action( 'admin_notices', array( $check, 'wc_untested' ) ) );
		$check->wc_untested();

		$this->assertFalse( WC_Admin_Notices::has_notice( PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME . '-woocommerce-untested' ) );
	}

	/**
	 * Previously stored warnings must disappear without clearing other notices.
	 */
	public function test_stored_untested_woocommerce_notice_is_removed() {
		$slug  = PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME . '-woocommerce-untested';
		$other = 'compatibility-fixture/fixture.php-woocommerce-untested';
		WC_Admin_Notices::add_custom_notice( $slug, 'Stored Pinterest warning.' );
		WC_Admin_Notices::add_custom_notice( $other, 'Other plugin warning.' );

		// Run only the registered cleanup, without unrelated admin_init side effects.
		$callback = array( Pinterest_For_Woocommerce::instance(), 'remove_untested_woocommerce_notice' );
		$this->assertSame( 10, has_action( 'admin_init', $callback ) );
		call_user_func( $callback );

		$this->assertFalse( WC_Admin_Notices::has_notice( $slug ) );
		$this->assertTrue( WC_Admin_Notices::has_notice( $other ) );
		ob_start();
		WC_Admin_Notices::output_custom_notices();
		$output = ob_get_clean();
		$this->assertStringNotContainsString( 'Stored Pinterest warning.', $output );
		$this->assertStringContainsString( 'Other plugin warning.', $output );
	}

	/**
	 * Other plugins' WooCommerce warnings and Pinterest's WordPress warnings remain.
	 */
	public function test_other_compatibility_notices_are_preserved() {
		set_current_screen( 'plugins' );
		$data             = Checker::instance()->get_plugin_data( PINTEREST_FOR_WOOCOMMERCE_PLUGIN_FILE, PINTEREST_FOR_WOOCOMMERCE_VERSION );
		$data['File']     = WP_PLUGIN_DIR . '/compatibility-fixture/fixture.php';
		$data['TestedWC'] = '1.0';
		$check            = new WCCompatibility();
		$this->assertTrue( $check->is_compatible( $data ) );
		$check->wc_untested();
		$this->assertTrue( WC_Admin_Notices::has_notice( 'compatibility-fixture/fixture.php-woocommerce-untested' ) );

		$data['File']     = PINTEREST_FOR_WOOCOMMERCE_PLUGIN_FILE;
		$data['TestedWP'] = '1.0';
		$check            = new WPCompatibility();
		$this->assertTrue( $check->is_compatible( $data ) );
		$check->wp_not_tested();
		$this->assertTrue( WC_Admin_Notices::has_notice( PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME . '-wordpress-untested' ) );
	}

	/**
	 * The checker must still reject WooCommerce below the required version.
	 */
	public function test_checker_still_rejects_incompatible_woocommerce() {
		$data               = Checker::instance()->get_plugin_data( PINTEREST_FOR_WOOCOMMERCE_PLUGIN_FILE, PINTEREST_FOR_WOOCOMMERCE_VERSION );
		$data['RequiresWC'] = '999.0';
		$check              = new WCCompatibility();

		$this->assertFalse( $check->is_compatible( $data ) );
		$this->assertSame( 10, has_action( 'admin_notices', array( $check, 'wc_out_of_date' ) ) );
		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Exercise the dependency's filter.
		$this->assertTrue( apply_filters( 'wc_grow_compat_check_show_admin_notice', true, PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME . '-woocommerce-out-of-date' ) );
	}

	/**
	 * Preserve prior filter decisions and unexpected values for every other slug.
	 *
	 * @dataProvider compatibility_notice_values
	 * @param mixed $show Previous filter value.
	 */
	public function test_other_notice_filter_values_are_preserved( $show ) {
		foreach ( array( 'woocommerce-not-installed', 'woocommerce-not-activated', 'woocommerce-out-of-date', 'woocommerce-upgrade-recommendation', 'wordpress-untested' ) as $notice ) {
			// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Exercise the dependency's filter.
			$this->assertSame( $show, apply_filters( 'wc_grow_compat_check_show_admin_notice', $show, PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME . '-' . $notice ) );
		}
		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Exercise the dependency's filter.
		$this->assertSame( $show, apply_filters( 'wc_grow_compat_check_show_admin_notice', $show, 'compatibility-fixture/fixture.php-woocommerce-untested' ) );
	}

	/** @return array Previous filter values, including unexpected customizations. */
	public function compatibility_notice_values() {
		return array_map(
			static function ( $value ) {
				return array( $value );
			},
			array( true, false, null, '', 'custom-value', array() )
		);
	}

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
			'below minimum' => array( '6.9.99', false ),
			'minimum'       => array( '7.0', true ),
		);
	}

	/**
	 * Checks the supported WooCommerce version boundary.
	 *
	 * @dataProvider woocommerce_version_provider
	 * @param string|null $version WooCommerce version to check, or null when missing.
	 * @param bool        $expected Whether the version is supported.
	 */
	public function test_woocommerce_version_requirement( $version, $expected ) {
		// A fresh process can define WC_VERSION without the bootstrap's immutable constant.
		$script = sprintf(
			<<<'PHP'
			<?php
			define( 'ABSPATH', %s );
			$wc_version = %s;
			if ( null !== $wc_version ) {
				define( 'WC_VERSION', $wc_version );
			}
			define( 'PINTEREST_FOR_WOOCOMMERCE_VERSION', %s );
			$wp_version = '7.0';
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
			'missing'       => array( null, false ),
			'below minimum' => array( '11.0.99', false ),
			'minimum'       => array( '11.1.0', true ),
		);
	}
}
