<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\PluginUpdate;

use ReflectionClass;
use \WC_Unit_Test_Case;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

use Automattic\WooCommerce\Pinterest\Logger;
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
	 * Clear =the update version option used to detect if the plugin has been updated.
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

			static $message = '';
			public function log( $level, $msg )
			{
				self::$message = $msg;
			}
		};
		Logger::$logger = $this->mock_logger;
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
			$method->invoke( $this->plugin_update, PINTEREST_FOR_WOOCOMMERCE_VERSION )
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
			$method->invoke( $this->plugin_update, PINTEREST_FOR_WOOCOMMERCE_VERSION )
		);
	}

	/**
	 * Test main update flow.
	 * perform_plugin_updates does not throw.
	 *
	 * @group update
	 * @return void
	 */
	public function testUpdateFlowNoThrow() {

		$mock_plugin_update = $this->getMockBuilder( PluginUpdate::class )
			->setMethods( ['perform_plugin_updates'] )
			->getMock();

		$mock_plugin_update->method('perform_plugin_updates')
			->willReturn( null );

		$mock_plugin_update->maybe_update();

		// No exception generated, logger message should be empty.
		$this->assertEmpty( $this->mock_logger::$message );

		$this->assertTrue( $this->plugin_update->plugin_is_up_to_date() );
	}

		/**
	 * Test main update flow.
	 * perform_plugin_updates does not throw.
	 *
	 * @group update
	 * @return void
	 */
	public function testUpdateFlowWithThrow() {

		$mock_plugin_update = $this->getMockBuilder( PluginUpdate::class )
			->setMethods( ['perform_plugin_updates'] )
			->getMock();

		$ex = new Exception( 'Veni, vidi, error!' );
		$mock_plugin_update->method('perform_plugin_updates')
			->willThrowException( $ex );

		$mock_plugin_update->maybe_update();

		// Exception was caught and logged;
		$this->assertEquals( "Plugin update to version 1.0.8 error: Veni, vidi, error!", $this->mock_logger::$message );

		/**
		 * Plugin should be marked as up to date. To avoid update loop.
		 * Check maybe_update for explanation why.
		 */
		$this->assertTrue( $this->plugin_update->plugin_is_up_to_date() );
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

