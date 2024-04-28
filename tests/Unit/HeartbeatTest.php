<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\Heartbeat;
use Pinterest_For_Woocommerce;

class HeartbeatTest extends \WP_UnitTestCase {

	/** @var Heartbeat */
	private $heartbeat;

	public function setUp(): void {
		parent::setUp();
		$this->heartbeat = new Heartbeat( WC()->queue() );
	}

	/**
	 * Tests feed generator registers the action scheduler failed execution hook.
	 *
	 * @return void
	 */
	public function test_cancel_jobs_removes_daily_and_hourly_as_actions() {
		// Jobs will schedule only if Pinterest is connected (means integration data is set and has the ID).
		Pinterest_For_Woocommerce::save_data( 'integration_data', array( 'id' => '567891567892' ) );

		$this->heartbeat->schedule_events();

		$this->assertTrue( as_has_scheduled_action( Heartbeat::HOURLY, array(), 'pinterest-for-woocommerce' ) );
		$this->assertTrue( as_has_scheduled_action( Heartbeat::DAILY, array(), 'pinterest-for-woocommerce' ) );

		$this->heartbeat->cancel_jobs();

		$this->assertFalse( as_has_scheduled_action( Heartbeat::HOURLY, array(), 'pinterest-for-woocommerce' ) );
		$this->assertFalse( as_has_scheduled_action( Heartbeat::DAILY, array(), 'pinterest-for-woocommerce' ) );
	}
}
