<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\FeedRegistration;
use Automattic\WooCommerce\Pinterest\LocalFeedConfigs;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

/**
 * Tests for the stale feed cleanup performed after feed registration.
 */
class FeedRegistrationTest extends WP_UnitTestCase {

	/**
	 * Feed IDs the fake Pinterest API received a DELETE request for.
	 *
	 * @var string[]
	 */
	private static $deleted_feed_ids = array();

	/**
	 * Feeds the fake Pinterest API returns for the merchant.
	 *
	 * @var array[]
	 */
	private static $remote_feeds = array();

	/**
	 * Sets up the test case.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		Pinterest_For_Woocommerce::set_default_settings();
		Pinterest_For_Woocommerce::save_setting( 'tracking_advertiser', '114141241212' );
		LocalFeedConfigs::deregister();

		self::$deleted_feed_ids = array();
		self::$remote_feeds     = self::default_feeds();
	}

	/**
	 * Tears down the test case.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();

		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'site_url' );
		LocalFeedConfigs::deregister();
	}

	/**
	 * Stale feed cleanup must not delete catalog feeds the plugin did not create,
	 * even when they are hosted on the same domain as the store.
	 *
	 * @return void
	 */
	public function test_stale_feed_cleanup_keeps_manual_feeds_hosted_on_the_site_domain() {
		add_filter( 'pre_http_request', array( self::class, 'fake_pinterest_api' ), 10, 3 );

		FeedRegistration::maybe_delete_stale_feeds_for_merchant( 'plugin-owned-feed-id' );

		$this->assertSame(
			array( 'plugin-owned-stale-feed-id' ),
			self::$deleted_feed_ids,
			'Only stale feeds created by the plugin should be deleted.'
		);
	}

	/**
	 * The disconnect flow calls the cleanup with an empty feed ID to remove every
	 * plugin owned feed. Manual feeds hosted on the same domain must survive it.
	 *
	 * @return void
	 */
	public function test_disconnect_cleanup_keeps_manual_feeds_hosted_on_the_site_domain() {
		add_filter( 'pre_http_request', array( self::class, 'fake_pinterest_api' ), 10, 3 );

		FeedRegistration::maybe_delete_stale_feeds_for_merchant( '' );

		$this->assertSame(
			array( 'plugin-owned-feed-id', 'plugin-owned-stale-feed-id' ),
			self::$deleted_feed_ids,
			'Only feeds created by the plugin should be deleted on disconnect.'
		);
	}

	/**
	 * Deactivating the plugin runs pinterest_for_woocommerce_deregister(), which disconnects
	 * the merchant while the access token is still valid. Manual feeds hosted on the store
	 * domain must not be deleted from Pinterest when the plugin is deactivated.
	 *
	 * @return void
	 */
	public function test_plugin_deactivation_keeps_manual_feeds_hosted_on_the_site_domain() {
		$this->connect_business_account();
		add_filter( 'pre_http_request', array( self::class, 'fake_pinterest_api' ), 10, 3 );

		pinterest_for_woocommerce_deregister();

		$this->assertNotContains(
			'manual-same-domain-feed-id',
			self::$deleted_feed_ids,
			'Deactivating the plugin should not delete manually configured catalog feeds.'
		);
	}

	/**
	 * The site URL is compared as a bare string prefix, so it matches locations that merely
	 * start with the same characters. A sibling domain and a separate install living in a
	 * subdirectory of the store both satisfy the check while belonging to another site.
	 *
	 * @return void
	 */
	public function test_stale_feed_cleanup_does_not_match_on_a_partial_site_url_prefix() {
		add_filter(
			'site_url',
			function () {
				return 'https://shop.test';
			}
		);

		self::$remote_feeds = array(
			array(
				'id'       => 'plugin-owned-feed-id',
				'location' => 'https://shop.test/wp-content/uploads/pinterest-for-woocommerce-Ab1cD2.xml',
			),
			array(
				'id'       => 'sibling-domain-feed-id',
				'location' => 'https://shop.testing.com/wp-content/uploads/pinterest-for-woocommerce-Zz9yX8.xml',
			),
			array(
				'id'       => 'subdirectory-install-feed-id',
				'location' => 'https://shop.test/staging/wp-content/uploads/pinterest-for-woocommerce-Yy8xW7.xml',
			),
		);

		add_filter( 'pre_http_request', array( self::class, 'fake_pinterest_api' ), 10, 3 );

		FeedRegistration::maybe_delete_stale_feeds_for_merchant( 'plugin-owned-feed-id' );

		$this->assertSame(
			array(),
			self::$deleted_feed_ids,
			'Feeds belonging to another site must not be deleted because their location shares a prefix with the site URL.'
		);
	}

	/**
	 * Puts the plugin in a connected business account state so that disconnect() runs
	 * the merchant cleanup instead of bailing out early.
	 *
	 * @return void
	 */
	private function connect_business_account() {
		Pinterest_For_Woocommerce::save_token_data( array( 'access_token' => 'some-fake-access-token' ) );
		Pinterest_For_Woocommerce::save_setting(
			'account_data',
			array(
				'id'         => '123456789',
				'is_partner' => true,
			)
		);
	}

	/**
	 * Feed list holding two plugin owned feeds, a manually configured feed hosted on the
	 * store domain and a manually configured feed hosted elsewhere.
	 *
	 * @return array[]
	 */
	private static function default_feeds(): array {
		$site_url = get_site_url();

		return array(
			array(
				'id'       => 'plugin-owned-feed-id',
				'location' => $site_url . '/wp-content/uploads/pinterest-for-woocommerce-Ab1cD2.xml',
			),
			array(
				'id'       => 'plugin-owned-stale-feed-id',
				'location' => $site_url . '/wp-content/uploads/pinterest-for-woocommerce-Ef3gH4.xml',
			),
			array(
				'id'       => 'manual-same-domain-feed-id',
				'location' => $site_url . '/wp-content/uploads/catalogs/manual-feed.xml',
			),
			array(
				'id'       => 'manual-external-feed-id',
				'location' => 'https://feeds.example.com/manual-feed.xml',
			),
		);
	}

	/**
	 * Fakes the Pinterest catalog feeds endpoints.
	 *
	 * Returns the feed list under test and records the feed IDs the code under test asks
	 * to delete.
	 *
	 * @param false|array $response Preempted response.
	 * @param array       $args     Request arguments.
	 * @param string      $url      Request URL.
	 * @return array
	 */
	public static function fake_pinterest_api( $response, $args, $url ) {
		if ( false !== strpos( $url, 'catalogs/feeds?' ) ) {
			return self::response( array( 'items' => self::$remote_feeds ) );
		}

		if ( 'DELETE' === $args['method'] && preg_match( '#catalogs/feeds/([^?]+)#', $url, $matches ) ) {
			self::$deleted_feed_ids[] = $matches[1];
			return self::response( array(), 204 );
		}

		return $response;
	}

	/**
	 * Builds a WordPress HTTP API response.
	 *
	 * @param array $body        Response body.
	 * @param int   $status_code Response status code.
	 * @return array
	 */
	private static function response( array $body, int $status_code = 200 ): array {
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $body ),
			'response' => array(
				'code'    => $status_code,
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
