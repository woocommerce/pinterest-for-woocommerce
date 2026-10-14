<?php
/**
 * Tests the V3 to V5 credential migration that runs when upgrading from a pre-1.4.0 version.
 *
 * @package Automattic\WooCommerce\Pinterest\Tests
 */

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use ActionScheduler_Store;
use Automattic\WooCommerce\Pinterest\Notes\TokenExchangeFailure;
use Automattic\WooCommerce\Pinterest\PluginUpdate;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

/**
 * Upgrades a store from before 1.4.0 through the plugin's own update entry point.
 *
 * The token exchange itself talks to the Pinterest API, which is not stood in for here: only a
 * store that must never reach it is covered. Any request it makes is refused and fails the test.
 *
 * @version x.x.x
 */
class TokenMigrationTest extends WP_UnitTestCase {

	/**
	 * Requests the upgrade attempted.
	 *
	 * @var string[]
	 */
	private $requests = array();

	/**
	 * Seeds a store last updated on a pre-1.4.0 version.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		update_option( PluginUpdate::PLUGIN_UPDATE_VERSION_OPTION, '1.3.9' );
		delete_option( PINTEREST_FOR_WOOCOMMERCE_PINTEREST_API_VERSION );

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
	 * Records and refuses any HTTP request: the stores tested here must not call out.
	 *
	 * @param mixed  $response Short-circuit value.
	 * @param array  $args     Request arguments.
	 * @param string $url      Request URL.
	 * @return \WP_Error
	 */
	public function refuse_request( $response, $args, $url ) {
		$this->requests[] = $args['method'] . ' ' . $url;

		return new \WP_Error( 'unexpected_request', 'This upgrade must not call out.' );
	}

	/**
	 * A store that was never connected has nothing to migrate.
	 *
	 * @return void
	 */
	public function test_upgrade_of_a_store_without_a_v3_token_requests_nothing() {
		Pinterest_For_Woocommerce::save_settings( array(), PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );

		Pinterest_For_Woocommerce()->maybe_update_plugin();

		$this->assertSame( array(), $this->requests );
		$this->assertSame( '', Pinterest_For_Woocommerce::get_api_version() );
		$this->assertSame(
			array(),
			as_get_scheduled_actions(
				array(
					'hook'     => PluginUpdate::TOKEN_UPDATE_RETRY_HOOK,
					'status'   => ActionScheduler_Store::STATUS_PENDING,
					'group'    => PINTEREST_FOR_WOOCOMMERCE_PREFIX,
					'per_page' => -1,
				)
			)
		);
		$this->assertFalse( TokenExchangeFailure::note_exists() );
		$this->assertSame( PINTEREST_FOR_WOOCOMMERCE_VERSION, get_option( PluginUpdate::PLUGIN_UPDATE_VERSION_OPTION ) );
	}
}
