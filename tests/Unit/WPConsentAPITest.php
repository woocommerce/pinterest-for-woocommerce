<?php

namespace Automattic\WooCommerce\Pinterest;

use WP_UnitTestCase;

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
			->onlyMethods( array( 'is_wp_consent_api_active', 'disable_tracking' ) )
			->getMock();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function tearDown(): void {
		parent::tearDown();
		unset( $this->wp_consent_api );
		remove_all_filters( 'woocommerce_pinterest_disable_tracking' );
		remove_all_filters( 'wp_consent_api_registered_' . PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME );
	}

	public function test_wp_consent_api_not_available(): void {
		$this->wp_consent_api->expects( $this->once() )
			->method( 'is_wp_consent_api_active' )
			->willReturn( false );

		$this->wp_consent_api->__construct();

		// When API is not available, no filters should be registered
		$this->assertFalse( has_filter( 'wp_consent_api_registered_' . PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME ) );
		$this->assertFalse( has_filter( 'woocommerce_pinterest_disable_tracking' ) );
	}

	public function test_wp_consent_api_available_with_marketing_consent(): void {
		$this->wp_consent_api->expects( $this->once() )
			->method( 'is_wp_consent_api_active' )
			->willReturn( true );

		$this->wp_consent_api->expects( $this->once() )
			->method( 'disable_tracking' )
			->willReturn( false );

		$this->wp_consent_api->__construct();

		// When API is available, both filters should be registered
		$this->assertTrue( has_filter( 'wp_consent_api_registered_' . PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME ) );
		$this->assertTrue( has_filter( 'woocommerce_pinterest_disable_tracking' ) );

		// Plugin registration filter should return true
		$this->assertTrue( apply_filters( 'wp_consent_api_registered_' . PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME, false ) );

		// When marketing consent is granted, tracking should be enabled
		$this->assertFalse( apply_filters( 'woocommerce_pinterest_disable_tracking', false ) );
	}

	public function test_wp_consent_api_available_without_marketing_consent(): void {
		$this->wp_consent_api->expects( $this->once() )
			->method( 'is_wp_consent_api_active' )
			->willReturn( true );

		$this->wp_consent_api->expects( $this->once() )
			->method( 'disable_tracking' )
			->willReturn( true );

		$this->wp_consent_api->__construct();

		// When API is available, both filters should be registered
		$this->assertTrue( has_filter( 'wp_consent_api_registered_' . PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME ) );
		$this->assertTrue( has_filter( 'woocommerce_pinterest_disable_tracking' ) );

		// When marketing consent is not granted, tracking should be disabled
		$this->assertTrue( apply_filters( 'woocommerce_pinterest_disable_tracking', false ) );
	}
}
