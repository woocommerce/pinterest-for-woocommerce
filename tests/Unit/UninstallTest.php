<?php
/**
 * Tests what uninstalling the plugin removes and what it leaves behind.
 *
 * @package Automattic\WooCommerce\Pinterest\Tests
 */

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\Crypto;
use Automattic\WooCommerce\Pinterest\FeedGenerator;
use Automattic\WooCommerce\Pinterest\Heartbeat;
use Automattic\WooCommerce\Pinterest\Notes\TokenExchangeFailure;
use Automattic\WooCommerce\Pinterest\PluginUpdate;
use Pinterest_For_Woocommerce;
use WC_Helper_Product;
use WP_UnitTestCase;

/**
 * Runs the real uninstall.php against a seeded store.
 *
 * The test bootstrap already defines WP_UNINSTALL_PLUGIN, which the file requires.
 *
 * @version 1.5.4
 */
class UninstallTest extends WP_UnitTestCase {

	/**
	 * Options the plugin deletes only when the merchant asked for its data to be erased.
	 */
	private const ERASABLE_OPTIONS = array(
		'pinterest_for_woocommerce',
		'pinterest_for_woocommerce_marketing_notifications_init_timestamp',
		'pinterest_for_woocommerce_account_connection_timestamp',
		'pinterest_for_woocommerce_pinterest_api_version',
		'pinterest-for-woocommerce-update-version',
	);

	/**
	 * Background jobs the plugin owns.
	 */
	private const PLUGIN_JOBS = array(
		'pinterest-for-woocommerce-handle-sync',
		'pinterest-for-woocommerce-feed-generation',
		FeedGenerator::ACTION_START_FEED_GENERATOR,
		Heartbeat::DAILY,
	);

	/**
	 * Requests the uninstall attempted.
	 *
	 * @var string[]
	 */
	private $requests = array();

	/**
	 * Seeds the options and jobs of a store that used the plugin without a business account.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		update_option( 'pinterest_for_woocommerce_marketing_notifications_init_timestamp', 1700000000 );
		update_option( 'pinterest_for_woocommerce_account_connection_timestamp', 1700000100 );
		update_option( 'pinterest_for_woocommerce_pinterest_api_version', 'v5' );
		update_option( PluginUpdate::PLUGIN_UPDATE_VERSION_OPTION, PINTEREST_FOR_WOOCOMMERCE_VERSION );
		Pinterest_For_Woocommerce::save_settings(
			array(
				'token_data'  => array( 'access_token' => Crypto::encrypt( 'access-token' ) ),
				'merchant_id' => '1479839719476',
			),
			PINTEREST_FOR_WOOCOMMERCE_DATA_NAME
		);

		foreach ( self::PLUGIN_JOBS as $hook ) {
			as_schedule_single_action( time() + HOUR_IN_SECONDS, $hook, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		}

		add_filter( 'pre_http_request', array( $this, 'refuse_request' ), 10, 3 );
	}

	/**
	 * Reloads the plugin's in-memory settings, which the rolled back transaction does not reset.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();

		Pinterest_For_Woocommerce::get_settings( true );
		Pinterest_For_Woocommerce::get_settings( true, PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );
	}

	/**
	 * Records and refuses any HTTP request: none of these stores has a business account to disconnect.
	 *
	 * @param mixed  $response Short-circuit value.
	 * @param array  $args     Request arguments.
	 * @param string $url      Request URL.
	 * @return \WP_Error
	 */
	public function refuse_request( $response, $args, $url ) {
		$this->requests[] = $args['method'] . ' ' . $url;

		return new \WP_Error( 'unexpected_request', 'Uninstalling this store must not call out.' );
	}

	/**
	 * With "Erase plugin data" on, nothing of the plugin is left and nothing else is touched.
	 *
	 * @return void
	 */
	public function test_uninstall_with_erase_enabled_removes_the_plugin_data_only() {
		Pinterest_For_Woocommerce::save_settings( self::merchant_settings( true ) );
		TokenExchangeFailure::possibly_add_note();

		$product = WC_Helper_Product::create_simple_product();
		update_option( 'woocommerce_currency', 'EUR' );
		update_option( 'pinterest_importer_by_someone_else', 'keep' );
		as_schedule_single_action( time() + HOUR_IN_SECONDS, 'another_plugin_job', array(), 'another-plugin' );

		$this->uninstall();

		foreach ( self::ERASABLE_OPTIONS as $option ) {
			$this->assertFalse( get_option( $option ), "$option is still stored." );
		}
		$this->assertFalse( get_option( 'pinterest_for_woocommerce_data' ) );
		$this->assertSame( array(), $this->scheduled_plugin_jobs() );
		$this->assertFalse( TokenExchangeFailure::note_exists() );

		$this->assertInstanceOf( \WC_Product::class, wc_get_product( $product->get_id() ) );
		$this->assertSame( 'EUR', get_option( 'woocommerce_currency' ) );
		$this->assertSame( 'keep', get_option( 'pinterest_importer_by_someone_else' ) );
		$this->assertTrue( as_has_scheduled_action( 'another_plugin_job', array(), 'another-plugin' ) );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * With "Erase plugin data" off, the merchant's settings wait for a reinstall, while the
	 * connection and the background jobs do not outlive the plugin.
	 *
	 * @return void
	 */
	public function test_uninstall_with_erase_disabled_keeps_the_settings_and_drops_the_connection() {
		Pinterest_For_Woocommerce::save_settings( self::merchant_settings( false ) );

		$this->uninstall();

		$this->assertSame(
			array(
				'erase_plugin_data'    => false,
				'save_to_pinterest'    => false,
				'rich_pins_on_posts'   => true,
				'enable_debug_logging' => true,
				// Identifies the disconnected Pinterest account, so it is cleared.
				'tracking_advertiser'  => null,
				'tracking_tag'         => null,
				'account_data'         => null,
			),
			get_option( 'pinterest_for_woocommerce' )
		);
		$this->assertSame( 1700000000, get_option( 'pinterest_for_woocommerce_marketing_notifications_init_timestamp' ) );
		$this->assertSame( 1700000100, get_option( 'pinterest_for_woocommerce_account_connection_timestamp' ) );
		$this->assertSame( 'v5', get_option( 'pinterest_for_woocommerce_pinterest_api_version' ) );
		$this->assertSame( PINTEREST_FOR_WOOCOMMERCE_VERSION, get_option( PluginUpdate::PLUGIN_UPDATE_VERSION_OPTION ) );

		// The access token lives here.
		$this->assertFalse( get_option( 'pinterest_for_woocommerce_data' ) );
		$this->assertSame( array(), $this->scheduled_plugin_jobs() );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * A store that never saved the plugin's settings uninstalls without a PHP warning.
	 *
	 * @return void
	 */
	public function test_uninstall_of_a_store_without_saved_settings_completes() {
		delete_option( 'pinterest_for_woocommerce' );
		Pinterest_For_Woocommerce::get_settings( true );

		$this->uninstall();

		// Not erased: the merchant never asked for that.
		$this->assertSame( 'v5', get_option( 'pinterest_for_woocommerce_pinterest_api_version' ) );
		$this->assertFalse( get_option( 'pinterest_for_woocommerce_data' ) );
		$this->assertSame( array(), $this->scheduled_plugin_jobs() );
	}

	/**
	 * Runs the plugin's uninstall routine the way WordPress does, by including the file.
	 *
	 * @return void
	 */
	private function uninstall(): void {
		include dirname( __DIR__, 2 ) . '/uninstall.php';
	}

	/**
	 * Settings a merchant saved, with a tracked account but no business account connected.
	 *
	 * @param bool $erase_plugin_data The "Erase plugin data" setting.
	 * @return array
	 */
	private static function merchant_settings( bool $erase_plugin_data ): array {
		return array(
			'erase_plugin_data'    => $erase_plugin_data,
			'save_to_pinterest'    => false,
			'rich_pins_on_posts'   => true,
			'enable_debug_logging' => true,
			'tracking_advertiser'  => '549765662491',
			'tracking_tag'         => '2613286171854',
			'account_data'         => array(
				'id'         => '123456789',
				'is_partner' => false,
			),
		);
	}

	/**
	 * The plugin's jobs still waiting in the queue.
	 *
	 * @return string[] Hook names.
	 */
	private function scheduled_plugin_jobs(): array {
		return array_values(
			array_filter(
				self::PLUGIN_JOBS,
				function ( $hook ) {
					return as_has_scheduled_action( $hook, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
				}
			)
		);
	}
}
