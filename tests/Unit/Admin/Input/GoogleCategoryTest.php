<?php
/**
 * Tests the Google category input.
 *
 * @package Automattic\WooCommerce\Pinterest\Tests
 */

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Admin\Input;

use Automattic\WooCommerce\Pinterest\Admin\Input\GoogleCategory;
use WP_UnitTestCase;

/**
 * Setting the input's options.
 *
 * @version 1.6.0
 */
class GoogleCategoryTest extends WP_UnitTestCase {

	/**
	 * Setting options returns the same input, for chaining, and the view data lists them.
	 *
	 * @return void
	 */
	public function test_set_options_returns_the_input_and_stores_the_options() {
		$input   = new GoogleCategory();
		$options = array( '499954' => 'Animals & Pet Supplies' );

		$this->assertSame( $input, $input->set_options( $options ) );
		$this->assertSame( $options, $input->get_options() );
		$this->assertSame( $options, $input->get_view_data()['options'] );
	}
}
