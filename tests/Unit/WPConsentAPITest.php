<?php

namespace Automattic\WooCommerce\Pinterest;

use WP_UnitTestCase;

require_once dirname( __DIR__ ) . '/Helpers/WPConsentAPIStub.php';

/**
 * Class WPConsentAPITest.
 *
 * @version 1.6.3
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
			->onlyMethods(
				array(
					'is_wp_consent_api_active',
					'should_disable_tracking_for_user_consent',
					'should_disable_conversions_api_for_user_consent',
				)
			)
			->getMock();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function tearDown(): void {
		parent::tearDown();
		unset( $this->wp_consent_api );
		remove_all_filters( 'woocommerce_pinterest_disable_tracking_user_consent' );
		unset( $GLOBALS['pinterest_test_marketing_consent'] );
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
		$this->assertFalse( has_filter( 'woocommerce_pinterest_disable_tracking_user_consent' ) );
		$this->assertFalse( has_filter( 'woocommerce_pinterest_disable_conversions_api_user_consent' ) );
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
			->method( 'should_disable_tracking_for_user_consent' )
			->willReturn( false );

		$this->wp_consent_api->__construct();

		// When API is available, both filters should be registered.
		$this->assertTrue( has_filter( 'wp_consent_api_registered_' . PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME ) );
		$this->assertTrue( has_filter( 'woocommerce_pinterest_disable_tracking_user_consent' ) );
		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		$this->assertTrue( apply_filters( 'wp_consent_api_registered_' . PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME, false ) );

		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		$this->assertFalse( apply_filters( 'woocommerce_pinterest_disable_tracking_user_consent', false ) );
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
			->method( 'should_disable_tracking_for_user_consent' )
			->willReturn( true );

		$this->wp_consent_api->__construct();

		// When API is available, both filters should be registered.
		$this->assertTrue( has_filter( 'wp_consent_api_registered_' . PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME ) );
		$this->assertTrue( has_filter( 'woocommerce_pinterest_disable_tracking_user_consent' ) );

		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		$this->assertTrue( apply_filters( 'woocommerce_pinterest_disable_tracking_user_consent', false ) );
	}

	/**
	 * Test that user consent tracking can be disabled.
	 *
	 * @return void
	 */
	public function test_user_consent_tracking_disabled_by_default(): void {
		$this->assertFalse(
			//	phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
			apply_filters( 'woocommerce_pinterest_disable_tracking_user_consent', false ),
			'User consent tracking should not be disabled by default'
		);
	}

	/**
	 * Test user consent tracking control via filter.
	 *
	 * @return void
	 */
	public function test_user_consent_tracking_control(): void {
		// By default, user consent tracking should not be disabled.
		$this->assertFalse(
			// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
			apply_filters( 'woocommerce_pinterest_disable_tracking_user_consent', false ),
			'User consent tracking should not be disabled by default'
		);

		// Can be disabled via filter.
		add_filter( 'woocommerce_pinterest_disable_tracking_user_consent', '__return_true' );
		$this->assertTrue(
			// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
			apply_filters( 'woocommerce_pinterest_disable_tracking_user_consent', false ),
			'User consent tracking should be disabled when filter returns true'
		);
		remove_filter( 'woocommerce_pinterest_disable_tracking_user_consent', '__return_true' );
	}

	/**
	 * The Conversions API consent filter is routed to its own callback, not the
	 * Tag consent callback a cached store may override.
	 *
	 * @return void
	 */
	public function test_conversions_api_consent_filter_uses_its_own_callback(): void {
		$this->wp_consent_api->method( 'is_wp_consent_api_active' )->willReturn( true );
		$this->wp_consent_api->expects( $this->never() )
			->method( 'should_disable_tracking_for_user_consent' );
		$this->wp_consent_api->expects( $this->once() )
			->method( 'should_disable_conversions_api_for_user_consent' )
			->willReturn( true );

		$this->wp_consent_api->__construct();

		$this->assertTrue( has_filter( 'woocommerce_pinterest_disable_conversions_api_user_consent' ) );
		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		$this->assertTrue( apply_filters( 'woocommerce_pinterest_disable_conversions_api_user_consent', false ) );
	}

	/**
	 * Both consent callbacks follow the API when available and allow tracking without it.
	 *
	 * @dataProvider marketing_consent_states
	 *
	 * @param mixed $consent       Value returned by wp_has_consent().
	 * @param bool  $should_skip   Whether tracking should be skipped.
	 * @param bool  $api_available Whether WP Consent API is available.
	 * @return void
	 */
	public function test_consent_callbacks_follow_marketing_consent( $consent, bool $should_skip, bool $api_available ): void {
		$GLOBALS['pinterest_test_marketing_consent'] = $consent;

		$consent_api = $this->getMockBuilder( WPConsentAPI::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'is_wp_consent_api_active' ) )
			->getMock();
		$consent_api->method( 'is_wp_consent_api_active' )->willReturn( $api_available );

		$this->assertSame( $should_skip, $consent_api->should_disable_tracking_for_user_consent() );
		$this->assertSame( $should_skip, $consent_api->should_disable_conversions_api_for_user_consent( false ) );
	}

	/**
	 * @return array Consent results, expected outcomes, and API availability.
	 */
	public function marketing_consent_states(): array {
		return array(
			'consent given'        => array( true, false, true ),
			'consent refused'      => array( false, true, true ),
			'filtered to null'     => array( null, true, true ),
			'filtered to a string' => array( '1', false, true ),
			'API unavailable'      => array( false, false, false ),
		);
	}

	/**
	 * Granted marketing consent does not override an earlier callback that skips
	 * Conversions API events.
	 *
	 * @return void
	 */
	public function test_conversions_api_consent_callback_keeps_earlier_skip(): void {
		$GLOBALS['pinterest_test_marketing_consent'] = true;

		$this->assertTrue( ( new WPConsentAPI() )->should_disable_conversions_api_for_user_consent( true ) );
	}
}
