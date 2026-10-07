<?php

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Guards the order storage selected for the test run.
 *
 * @version 1.5.2
 */
class OrderStorageTest extends \WP_UnitTestCase {

	/**
	 * A green HPOS run must have exercised HPOS, and every other run legacy storage.
	 */
	public function test_suite_runs_in_requested_order_storage() {
		$this->assertSame(
			'hpos' === getenv( 'PINTEREST_FOR_WOOCOMMERCE_TEST_ORDER_STORAGE' ),
			OrderUtil::custom_orders_table_usage_is_enabled()
		);
	}
}
