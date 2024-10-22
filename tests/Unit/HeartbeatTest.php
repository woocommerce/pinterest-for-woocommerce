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
		Pinterest_For_Woocommerce::save_token_data( array( 'access_token' => 'def5020011e6faae77c53f97dc7d36875a55ae0402fbc99e28e3b2a43580562947330a5d31fa766cca66a0a1accfb72ffcc8106e1d57b2ee68a07b34d9158a4e70e164d035c80e63a77be40fd9d0955c6aa36ff96ffcce80a4dfc2963d3511e1c71ee76b766d8f26f53a713ab3b28b3e17053e9afd0e5dd1ccedecace7514e471641ea298631edcf0310294488da09a8ae0a1a3f9fdfd5fe1d3f215be42108e064cd5baea7d6f4eb970551aaf480cc986a1b0c7bfa83df5580' ) );

		$this->heartbeat->schedule_events();

		$this->assertTrue( as_has_scheduled_action( Heartbeat::HOURLY, array(), 'pinterest-for-woocommerce' ) );
		$this->assertTrue( as_has_scheduled_action( Heartbeat::DAILY, array(), 'pinterest-for-woocommerce' ) );

		$this->heartbeat->cancel_jobs();

		$this->assertFalse( as_has_scheduled_action( Heartbeat::HOURLY, array(), 'pinterest-for-woocommerce' ) );
		$this->assertFalse( as_has_scheduled_action( Heartbeat::DAILY, array(), 'pinterest-for-woocommerce' ) );
	}
}
