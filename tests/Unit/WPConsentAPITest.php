<?php

namespace Automattic\WooCommerce\Pinterest;

use WP_UnitTestCase;

/**
 * Class WPConsentAPITest.
 */
class WPConsentAPITest extends WP_UnitTestCase {

	/**
	 * @var WPConsentAPI
	 */
	private WPConsentAPI $wp_consent_api;

	/**
	 * {@inheritdoc}
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->wp_consent_api = $this->getMockBuilder( WPConsentAPI::class )
			->onlyMethods( array( 'is_wp_consent_api_active', 'should_disable_tracking' ) )
			->getMock();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function tearDown(): void {
		parent::tearDown();
		unset( $this->wp_consent_api );
		remove_all_filters( 'woocommerce_pinterest_disable_conversions_capi_tracking' );
		remove_all_filters( 'wp_consent_api_registered_' . PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME );
	}

	/**
	 * Test that WP Consent API is not available.
	 * @return void
	 */
	public function test_wp_consent_api_not_available(): void {
		$this->wp_consent_api->expects( $this->once() )
			->method( 'is_wp_consent_api_active' )
			->willReturn( false );

		$this->wp_consent_api->__construct();

		// When API is not available, no filters should be registered.
		$this->assertFalse( has_filter( 'wp_consent_api_registered_' . PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME ) );
		$this->assertFalse( has_filter( 'woocommerce_pinterest_disable_conversions_capi_tracking' ) );
	}

	/**
	 * Test that WP Consent API is available.
	 * @return void
	 */
	public function test_wp_consent_api_available_with_marketing_consent(): void {
		$this->wp_consent_api->expects( $this->once() )
			->method( 'is_wp_consent_api_active' )
			->willReturn( true );

		$this->wp_consent_api->expects( $this->once() )
			->method( 'should_disable_tracking' )
			->willReturn( false );

		$this->wp_consent_api->__construct();

		// When API is available, both filters should be registered.
		$this->assertTrue( has_filter( 'wp_consent_api_registered_' . PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME ) );
		$this->assertTrue( has_filter( 'woocommerce_pinterest_disable_conversions_capi_tracking' ) );
		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		$this->assertTrue( apply_filters( 'wp_consent_api_registered_' . PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME, false ) );

		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		$this->assertFalse( apply_filters( 'woocommerce_pinterest_disable_conversions_capi_tracking', false ) );
	}

	/**
	 * Test that WP Consent API is available but marketing consent is not granted.
	 * @return void
	 */
	public function test_wp_consent_api_available_without_marketing_consent(): void {
		$this->wp_consent_api->expects( $this->once() )
			->method( 'is_wp_consent_api_active' )
			->willReturn( true );

		$this->wp_consent_api->expects( $this->once() )
			->method( 'should_disable_tracking' )
			->willReturn( true );

		$this->wp_consent_api->__construct();

		// When API is available, both filters should be registered.
		$this->assertTrue( has_filter( 'wp_consent_api_registered_' . PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME ) );
		$this->assertTrue( has_filter( 'woocommerce_pinterest_disable_conversions_capi_tracking' ) );

		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		$this->assertTrue( apply_filters( 'woocommerce_pinterest_disable_conversions_capi_tracking', false ) );
	}

	/**
	 * Test that CAPI tracking can be disabled.
	 *
	 * @return void
	 */
	public function test_capi_tracking_disabled_by_default(): void {
		$this->assertFalse(
			//	phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
			apply_filters( 'woocommerce_pinterest_disable_conversions_capi_tracking', false ),
			'CAPI tracking should be disabled by default'
		);
	}

	/**
	 * Test CAPI tracking control via filter.
	 *
	 * @return void
	 */
	public function test_capi_tracking_control(): void {
		// By default, CAPI tracking should not be disabled.
		$this->assertFalse(
			// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
			apply_filters( 'woocommerce_pinterest_disable_conversions_capi_tracking', false ),
			'CAPI tracking should not be disabled by default'
		);

		// Can be disabled via filter.
		add_filter( 'woocommerce_pinterest_disable_conversions_capi_tracking', '__return_true' );
		$this->assertTrue(
			// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
			apply_filters( 'woocommerce_pinterest_disable_conversions_capi_tracking', false ),
			'CAPI tracking should be disabled when filter returns true'
		);
		remove_filter( 'woocommerce_pinterest_disable_conversions_capi_tracking', '__return_true' );
	}
}
