<?php

use Yoast\PHPUnitPolyfills\TestCases\TestCase;
use Automattic\WooCommerce\Pinterest\LocaleMapper;

/**
 * Class for testing locale mapper.
 */
class PinterestTestLocaleMapper extends TestCase {

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
		add_filter( 'locale', array( $this, 'locale_filter' ) );
	}

	/**
	 * Remove the filter for the locale.
	 */
	protected function tearDown(): void
	{
		remove_filter( 'locale', array( $this, 'locale_filter' ) );
	}

	/**
	 * Test that the locale that matches is mapped correctly.
	 * @group locale_mapper
	 */
	public function test_locale_with_full_match() {
		$this->locale = 'en_US';
		$this->assertEquals( 'en-US', LocaleMapper::get_locale_for_api() );
	}

	/**
	 * Test that the locale that should match partially is mapped correctly.
	 * @group locale_mapper
	 */
	public function test_locale_with_partial_match() {
		$this->locale = 'de_DE';
		$this->assertEquals( 'de', LocaleMapper::get_locale_for_api() );
	}

	/**
	 * Test that the locale that does not match throws an exception.
	 * @group locale_mapper
	 */
	public function test_locale_with_no_match() {
		$this->locale = 'me_ME';
		$this->expectException( Exception::class );
		LocaleMapper::get_locale_for_api();
	}
}

