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
	}

	/**
	 * Tears down the test case.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();

		remove_all_filters( 'pre_http_request' );
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
	 * Fakes the Pinterest catalog feeds endpoints.
	 *
	 * Returns a feed list holding two plugin owned feeds, a manually configured feed hosted
	 * on the store domain and a manually configured feed hosted elsewhere. Records the feed
	 * IDs the code under test asks to delete.
	 *
	 * @param false|array $response Preempted response.
	 * @param array       $args     Request arguments.
	 * @param string      $url      Request URL.
	 * @return array
	 */
	public static function fake_pinterest_api( $response, $args, $url ) {
		if ( false !== strpos( $url, 'catalogs/feeds?' ) ) {
			$site_url = get_site_url();
			return self::response(
				array(
					'items' => array(
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
					),
				)
			);
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
