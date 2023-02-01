<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\PluginUpdate;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;
use Automattic\WooCommerce\Pinterest\LocaleMapper;

use Exception;

/**
 * Plugin Update Procedures test class.
 */
class Pinterest_Test_LocaleMapper extends TestCase {

	/**
	 * Test that the locale is mapped correctly.
	 * @group locale_mapper
	 */
	public function testLocaleMatch() {
		$locale_filter = function() {
			return 'en_US';
		};

		add_filter( 'pre_determine_locale', $locale_filter );

		$this->assertEquals( 'en-US', LocaleMapper::get_locale_for_api() );

		remove_filter( 'pre_determine_locale', $locale_filter );
	}

		/**
	 * Test that the locale is mapped correctly.
	 * @group locale_mapper
	 */
	public function testLocalePartialMatch() {
		$locale_filter = function() {
			return 'de_DE';
		};

		add_filter( 'pre_determine_locale', $locale_filter );

		$this->assertEquals( 'de', LocaleMapper::get_locale_for_api() );

		remove_filter( 'pre_determine_locale', $locale_filter );
	}
}

