<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Exception;

use Automattic\WooCommerce\Pinterest\Exception\InvalidClass;
use Automattic\WooCommerce\Pinterest\Exception\ValidateInterface;
use Automattic\WooCommerce\Pinterest\Product\Attributes\AttributeInterface;
use Automattic\WooCommerce\Pinterest\Product\Attributes\Condition;
use WP_UnitTestCase;

/**
 * Class validation reports invalid classes with InvalidClass.
 *
 * @version x.x.x
 */
class ValidateInterfaceTest extends WP_UnitTestCase {

	/**
	 * A class that does not exist, e.g. one added through a filter, throws InvalidClass instead of a TypeError.
	 */
	public function test_validate_interface_throws_invalid_class_for_missing_class() {
		$validator = $this->get_validator();

		// class_implements() warns about the missing class before returning false; that warning is not under test.
		set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Swallow only the expected class_implements() warning.
			static function ( $errno, $errstr ) {
				return E_WARNING === $errno && false !== strpos( $errstr, 'does not exist and could not be loaded' );
			}
		);
		try {
			$this->expectException( InvalidClass::class );
			$validator->validate( 'Automattic\WooCommerce\Pinterest\Tests\MissingAttribute', AttributeInterface::class );
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * A class that implements the interface passes.
	 */
	public function test_validate_interface_accepts_implementing_class() {
		$this->get_validator()->validate( Condition::class, AttributeInterface::class );
		$this->addToAssertionCount( 1 );
	}

	/**
	 * A class that does not implement the interface throws InvalidClass.
	 */
	public function test_validate_interface_throws_invalid_class_for_class_without_interface() {
		$this->expectException( InvalidClass::class );
		$this->get_validator()->validate( \stdClass::class, AttributeInterface::class );
	}

	/**
	 * Object exposing the trait's protected validator.
	 *
	 * @return object
	 */
	private function get_validator() {
		return new class() {
			use ValidateInterface;

			/**
			 * Run validate_interface().
			 *
			 * @param string $class_name     Class name.
			 * @param string $interface_name Interface name.
			 * @return void
			 */
			public function validate( string $class_name, string $interface_name ) {
				$this->validate_interface( $class_name, $interface_name );
			}
		};
	}
}
