<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\PluginUpdate;

use ReflectionClass;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

use Automattic\WooCommerce\Pinterest\Logger;
use Automattic\WooCommerce\Pinterest\FeedGenerator;
use Pinterest_For_Woocommerce;
use Automattic\WooCommerce\Pinterest\PluginUpdate;
use Exception;

/**
 * Plugin Update Procedures test class.
 */
class Pinterest_Test_Plugin_Update extends TestCase {

	/**
	 * Variable that holds the plugin update object used by tests.
	 *
	 * @var PluginUpdate|null
	 */
	private $plugin_update = null;

	/**
	 * Mocked logger.
	 *
	 * @var object|null Mocked logger object.
	 */
	private $mock_logger = null;

	/**
	 * Current plugin version.
	 * Used to make test version agnostic.
	 *
	 * @var string Plugin version string.
	 */
	private $current_version = PINTEREST_FOR_WOOCOMMERCE_VERSION;

	/**
	 * Clear the update version option used to detect if the plugin has been updated.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		delete_option( PluginUpdate::PLUGIN_UPDATE_VERSION_OPTION );
		$this->plugin_update = new PluginUpdate();

		/**
		 * Mock logger object that will catch any logged messages.
		 */
		$this->mock_logger = new class {

			public $message = array();
			public function log( $level, $msg )
			{
				$this->message[] = $msg;
			}
		};
		Logger::$logger = $this->mock_logger;
	}

	/**
	 * Clear upgrade state created by non-transactional updater tests.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		delete_option( FeedGenerator::OPTION_FEED_DIRTY );
		as_unschedule_all_actions( FeedGenerator::ACTION_START_FEED_GENERATOR, null, PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		parent::tearDown();
	}

	/**
	 * plugin_is_up_to_date test before update.
	 * When the method is called before the update procedure it should return false.
	 *
	 * @group update
	 */
	public function testPluginUpToDateDefault() {
		$this->assertFalse( $this->plugin_update->plugin_is_up_to_date() );
	}

	/**
	 * Method that finalizes the update procedure.
	 * After this gets called the plugin_is_up_to_date() should return true.
	 *
	 * @group update
	*/
	public function testUpdatePluginUpdateVersionOption() {
		$this->call_update_plugin_update_version_option();
		$this->assertTrue( $this->plugin_update->plugin_is_up_to_date() );
	}

	/**
	 * During update procedure update method to the latest version should be called.
	 *
	 * @group update
	 * @return void
	 */
	public function testVersionNeedsUpdate__OlderVersion() {
		$method = ( new ReflectionClass( PluginUpdate::class ) )->getMethod( 'version_needs_update' );
		$method->setAccessible( true );
		$this->assertTrue(
			$method->invoke( $this->plugin_update, $this->current_version )
		);
	}

	/**
	 * During update procedure update to the method to which update has already
	 * happened should not be called. Simulated using the same version for the check.
	 *
	 * @group update
	 * @return void
	 */
	public function testVersionNeedsUpdate__SameVersion() {
		$this->call_update_plugin_update_version_option();
		$method = ( new ReflectionClass( PluginUpdate::class ) )->getMethod( 'version_needs_update' );
		$method->setAccessible( true );
		$this->assertFalse(
			$method->invoke( $this->plugin_update, $this->current_version )
		);
	}

	/**
	 * Test main update flow.
	 * perform_plugin_update_procedure does not throw.
	 *
	 * @group update
	 * @return void
	 */
	public function testUpdateFlowNoThrow() {

		$mock_plugin_update = $this->getMockBuilder( PluginUpdate::class )
			->setMethods( ['perform_plugin_update_procedure'] )
			->getMock();

		$mock_plugin_update->method('perform_plugin_update_procedure');

		$mock_plugin_update->maybe_update();

		// No exception generated, logger message should be empty.
		$this->assertEquals( "Plugin updated to version: {$this->current_version}.", $this->mock_logger->message[0] );

		$this->assertTrue( $this->plugin_update->plugin_is_up_to_date() );
	}

	/**
	 * Test main update flow.
	 * perform_plugin_update_procedure does not throw.
	 *
	 * @group update
	 * @return void
	 */
	public function testUpdateFlowWithThrow() {

		$mock_plugin_update = $this->getMockBuilder( PluginUpdate::class )
			->setMethods( ['domain_verification_migration'] )
			->getMock();

		$ex = new Exception( 'Veni, vidi, error!' );
		$mock_plugin_update->method( 'domain_verification_migration' )
			->willThrowException( $ex );

		$mock_plugin_update->maybe_update();

		// Exception was caught and logged.
		$this->assertEquals( "Plugin update to version {$this->current_version}. Procedure: domain_verification_migration. Error: Veni, vidi, error!", $this->mock_logger->message[0] );

		// Plugin update message logged.
		$this->assertEquals( "Plugin updated to version: {$this->current_version}.", $this->mock_logger->message[1] );

		/**
		 * Plugin should be marked as up to date. To avoid update loop.
		 * Check maybe_update for explanation why.
		 */
		$this->assertTrue( $this->plugin_update->plugin_is_up_to_date() );
	}

	/**
	 * Test that the update flow happens only one for one plugin version.
	 *
	 * @group update
	 * @return void
	 */
	public function testAfterUpdateTheUpdateIsNotExecutedAgain() {

		$mock_plugin_update = $this->getMockBuilder( PluginUpdate::class )
			->setMethods( ['perform_plugin_update_procedure'] )
			->getMock();

		$mock_plugin_update->method('perform_plugin_update_procedure');

		$mock_plugin_update->maybe_update();

		// Plugin has been updated.
		$this->assertEquals( "Plugin updated to version: {$this->current_version}.", $this->mock_logger->message[0] );

		// Clear the Logger messages.
		$this->mock_logger->message = array();

		// Run update again.
		$mock_plugin_update->maybe_update();

		// No messages means that the update procedure exited early.
		$this->assertEmpty( $this->mock_logger->message );

	}

	/**
	 * The versioned upgrade requests one refresh and retains daily scheduling.
	 *
	 * @return void
	 */
	public function testUpgradeMarksFeedDirtyOnce() {
		$settings = Pinterest_For_Woocommerce::get_settings();
		$hook     = FeedGenerator::ACTION_START_FEED_GENERATOR;
		Pinterest_For_Woocommerce::save_setting( 'account_data', array( 'verified_user_websites' => array( wp_parse_url( home_url(), PHP_URL_HOST ) ) ) );
		Pinterest_For_Woocommerce::save_setting( 'product_sync_enabled', true );
		update_option( PluginUpdate::PLUGIN_UPDATE_VERSION_OPTION, '1.4.20' );
		delete_option( FeedGenerator::OPTION_FEED_DIRTY );
		as_unschedule_all_actions( $hook, null, PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		as_schedule_recurring_action( time() + DAY_IN_SECONDS, DAY_IN_SECONDS, $hook, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );

		try {
			$this->plugin_update->maybe_update();
			$this->assertTrue( (bool) get_option( FeedGenerator::OPTION_FEED_DIRTY ) );
			$this->assertLessThanOrEqual( time(), as_next_scheduled_action( $hook, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX ) );
			$actions = as_get_scheduled_actions(
				array(
					'hook'   => $hook,
					'status' => 'pending',
					'group'  => PINTEREST_FOR_WOOCOMMERCE_PREFIX,
				)
			);
			$this->assertCount( 1, $actions );
			$this->assertSame( DAY_IN_SECONDS, reset( $actions )->get_schedule()->get_recurrence() );

			delete_option( FeedGenerator::OPTION_FEED_DIRTY );
			$this->plugin_update->maybe_update();
			$this->assertFalse( get_option( FeedGenerator::OPTION_FEED_DIRTY ) );
		} finally {
			Pinterest_For_Woocommerce::save_settings( $settings );
		}
	}

	/**
	 * Stores with product sync disabled must not start a generation cycle.
	 *
	 * @return void
	 */
	public function testUpgradePreservesDisabledProductSync() {
		$settings = Pinterest_For_Woocommerce::get_settings();
		Pinterest_For_Woocommerce::save_setting( 'product_sync_enabled', false );
		update_option( PluginUpdate::PLUGIN_UPDATE_VERSION_OPTION, '1.4.20' );
		delete_option( FeedGenerator::OPTION_FEED_DIRTY );
		as_unschedule_all_actions( FeedGenerator::ACTION_START_FEED_GENERATOR, null, PINTEREST_FOR_WOOCOMMERCE_PREFIX );

		try {
			$this->plugin_update->maybe_update();
			$this->assertFalse( get_option( FeedGenerator::OPTION_FEED_DIRTY ) );
			$this->assertFalse( as_has_scheduled_action( FeedGenerator::ACTION_START_FEED_GENERATOR, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX ) );
		} finally {
			Pinterest_For_Woocommerce::save_settings( $settings );
		}
	}

	/**
	 * Later releases must not repeat this migration.
	 *
	 * @return void
	 */
	public function testLaterUpgradeDoesNotInvalidateFeedAgain() {
		update_option( PluginUpdate::PLUGIN_UPDATE_VERSION_OPTION, '1.5.2' );
		$updater = $this->getMockBuilder( PluginUpdate::class )->onlyMethods( array( 'invalidate_product_feeds' ) )->getMock();
		$updater->expects( $this->never() )->method( 'invalidate_product_feeds' );
		$updater->maybe_update();
	}

	/**
	 * Helper method for calling update_plugin_update_version_option.
	 *
	 * @return void
	 */
	private function call_update_plugin_update_version_option() {
		$method = ( new ReflectionClass( PluginUpdate::class ) )->getMethod( 'update_plugin_update_version_option' );
		$method->setAccessible( true );
		$method->invoke( $this->plugin_update );
	}

}

