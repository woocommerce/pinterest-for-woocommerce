<?php
/**
 * A non-select attribute with built-in options.
 *
 * @package Automattic\WooCommerce\Pinterest\Tests
 */

namespace Automattic\WooCommerce\Pinterest\Tests\Helpers;

use Automattic\WooCommerce\Pinterest\Product\Attributes\GoogleCategory;
use Automattic\WooCommerce\Pinterest\Product\Attributes\WithValueOptionsInterface;

/**
 * Attribute fixture for the form's value-options interface.
 */
class GoogleCategoryWithValueOptions extends GoogleCategory implements WithValueOptionsInterface {
	/**
	 * Return the attribute's value options.
	 *
	 * @return array
	 */
	public static function get_value_options(): array {
		return array( '1' => 'From attribute' );
	}
}
