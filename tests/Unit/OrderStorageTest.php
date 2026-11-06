<?php

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Guards the order storage selected for the test run.
 *
 * @version x.x.x
 */
class OrderStorageTest extends \WP_UnitTestCase {

	/**
	 * A green HPOS run must have exercised HPOS, and every other run legacy storage.
	 */
	public function test_suite_runs_in_requested_order_storage() {
		$storage = getenv( 'PINTEREST_FOR_WOOCOMMERCE_TEST_ORDER_STORAGE' );
		if ( false === $storage || '' === $storage ) {
			$storage = getenv( 'WC_ORDER_DATASTORE' );
		}

		$this->assertSame(
			'hpos' === $storage,
			OrderUtil::custom_orders_table_usage_is_enabled()
		);
	}
}
