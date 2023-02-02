<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\PluginUpdate;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;
use Automattic\WooCommerce\Pinterest\LocaleMapper;

use Exception;

/**
 * Class for testing locale mapper.
 */
class Pinterest_Test_LocaleMapper extends TestCase {

	private $locale;

	/**
	 * Method used for 'pre_determine_locale' filter.
	 * Using this allows us to modify the value that is used by
	 * the determine_locale() function in WordPress.
	 */
	public function locale_filter() {
		return $this->locale;
	}

	/**
	 * Set up the filter for the locale.
	 */
    protected function setUp(): void
    {
		add_filter( 'pre_determine_locale', array( $this, 'locale_filter' ) );
    }

	/**
	 * Remove the filter for the locale.
	 */
	protected function tearDown(): void
	{
		remove_filter( 'pre_determine_locale', array( $this, 'locale_filter' ) );
	}

	/**
	 * Test that the locale that matches is mapped correctly.
	 * @group locale_mapper
	 */
	public function testLocaleWithFullMatch() {
		$this->locale = 'en_US';
		$this->assertEquals( 'en-US', LocaleMapper::get_locale_for_api() );
	}

	/**
	 * Test that the locale that should match partially is mapped correctly.
	 * @group locale_mapper
	 */
	public function testLocaleWithPartialMatch() {
		$this->locale = 'de_DE';
		$this->assertEquals( 'de', LocaleMapper::get_locale_for_api() );
	}

	/**
	 * Test that the locale that does not matches throws an exception.
	 * @group locale_mapper
	 */
	public function testLocaleWithNolMatch() {
		$this->locale = 'me_ME';
		$this->expectException( Exception::class );
		LocaleMapper::get_locale_for_api();
	}
}

