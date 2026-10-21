<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\API\APIV5;
use Automattic\WooCommerce\Pinterest\FeedFileOperations;
use Automattic\WooCommerce\Pinterest\FeedOwnership;
use Automattic\WooCommerce\Pinterest\FeedRegistration;
use Automattic\WooCommerce\Pinterest\Feeds;
use Automattic\WooCommerce\Pinterest\LocaleMapper;
use Automattic\WooCommerce\Pinterest\LocalFeedConfigs;
use Automattic\WooCommerce\Pinterest\PinterestApiException;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

/**
 * Tests native registration, recovery and stale feed cleanup.
 *
 * @version 1.6.0
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
		delete_option( FeedOwnership::OPTION_NAME );
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
		delete_option( FeedOwnership::OPTION_NAME );
	}

	/**
	 * Registering to a feed that already exists on Pinterest records it as plugin owned, so
	 * feeds created before the ownership record existed get covered by the next registration run.
	 *
	 * @return void
	 */
	public function test_feed_registration_records_the_matched_feed_id() {
		Pinterest_For_Woocommerce::save_data(
			'local_feed_ids',
			array( Pinterest_For_Woocommerce::get_base_country() => 'Ab1cD2' )
		);
		self::$remote_feeds[0]['default_country'] = Pinterest_For_Woocommerce::get_base_country();
		self::$remote_feeds[0]['default_locale']  = LocaleMapper::get_locale_for_api();

		$feed_file_operations = $this->createMock( FeedFileOperations::class );
		$feed_file_operations->method( 'check_if_feed_file_exists' )->willReturn( true );
		add_filter( 'pre_http_request', array( self::class, 'fake_pinterest_api' ), 10, 3 );

		$registration = new FeedRegistration( LocalFeedConfigs::get_instance(), $feed_file_operations );
		$registration->handle_feed_registration();

		$this->assertSame( 'plugin-owned-feed-id', FeedRegistration::get_locally_stored_registered_feed_id() );
		$this->assertTrue( FeedOwnership::is_recorded( 'plugin-owned-feed-id' ) );
		$this->assertSame(
			array( 'plugin-owned-stale-feed-id' ),
			self::$deleted_feed_ids,
			'Registration still cleans up the stale plugin feed.'
		);
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
	 * A feed entry without an ID cannot be deleted, so the cleanup must skip it instead of
	 * sending a malformed DELETE request for an empty ID.
	 *
	 * @return void
	 */
	public function test_stale_feed_cleanup_skips_feeds_without_an_id() {
		self::$remote_feeds = array(
			array(
				'id'       => 'plugin-owned-feed-id',
				'location' => get_site_url() . '/wp-content/uploads/pinterest-for-woocommerce-Ab1cD2.xml',
			),
			array(
				'location' => get_site_url() . '/wp-content/uploads/pinterest-for-woocommerce-Ef3gH4.xml',
			),
			array(
				'id'       => '',
				'location' => get_site_url() . '/wp-content/uploads/pinterest-for-woocommerce-Ij5kL6.xml',
			),
		);

		add_filter( 'pre_http_request', array( self::class, 'fake_pinterest_api' ), 10, 3 );

		FeedRegistration::maybe_delete_stale_feeds_for_merchant( 'plugin-owned-feed-id' );

		$this->assertSame(
			array(),
			self::$deleted_feed_ids,
			'Feeds without an ID must not trigger a DELETE request.'
		);
	}

	/**
	 * A real temporary file cannot register; publication can reuse, create, or recover a feed.
	 *
	 * @dataProvider registration_workflows
	 * @param string $mode Workflow mode.
	 * @param array  $expected_requests Literal expected remote request sequence.
	 * @param int    $next_delay Expected next retry backoff.
	 * @param int    $ttl Expected error-cache lifetime.
	 * @return void
	 */
	public function test_registration_workflow_preserves_manual_feeds_and_invalidates_cached_lists(
		$mode,
		$expected_requests,
		$next_delay = 120,
		$ttl = 60
	) {
		$original_settings = get_option( PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME );
		$original_data     = get_option( PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );
		$original_country  = get_option( 'woocommerce_default_country' );
		$original_currency = get_option( 'woocommerce_currency' );
		$token_cache       = new \ReflectionProperty( \Automattic\WooCommerce\Pinterest\API\Base::class, 'token' );
		$token_cache->setAccessible( true );
		$original_token = $token_cache->getValue();
		$token_cache->setValue( null, null );
		$this->connect_business_account();
		$directory = wp_tempnam( 'pinterest-registration' );
		wp_delete_file( $directory );
		wp_mkdir_p( $directory );
		$uploads = function ( $data ) use ( $directory ) {
			$data['basedir'] = $directory;
			$data['path']    = $directory;
			$data['baseurl'] = 'https://shop.example/wp-content/uploads';
			$data['url']     = $data['baseurl'];
			$data['subdir']  = '';
			$data['error']   = false;
			return $data;
		};
		$name    = function () {
			return 'Coverage feed';
		};
		add_filter( 'upload_dir', $uploads );
		add_filter( 'pinterest_for_woocommerce_unique_feed_name', $name );
		update_option( 'woocommerce_default_country', 'US:CA' );
		update_option( 'woocommerce_currency', 'USD' );
		Pinterest_For_Woocommerce::save_data( 'local_feed_ids', array( 'US' => 'Rc7Ab3' ) );
		Pinterest_For_Woocommerce::save_data( 'feed_registered', false );
		if ( 'recover_after_success' === $mode ) {
			// This is the persisted value written by a previous successful Feeds::create_feed().
			Pinterest_For_Woocommerce::save_data( 'create_feed_delay', false );
		}
		Pinterest_For_Woocommerce::save_data( 'merchant_locale_not_valid', true );
		Pinterest_For_Woocommerce::save_data( 'merchant_connected_diff_platform', true );
		$location = 'https://shop.example/wp-content/uploads/pinterest-for-woocommerce-Rc7Ab3.xml';
		$owned    = array(
			'id'              => 'owned-current',
			'location'        => $location,
			'default_country' => 'US',
			'default_locale'  => 'en-US',
		);
		$created  = array_merge( $owned, array( 'id' => 'created-current' ) );
		$remote   = array(
			array(
				'id'       => 'owned-stale',
				'location' => 'https://shop.example/wp-content/uploads/pinterest-for-woocommerce-Ab1cD2.xml',
			),
			array(
				'id'       => 'manual-local',
				'location' => 'https://shop.example/wp-content/uploads/catalogs/manual.xml',
			),
			array(
				'id'       => 'manual-external',
				'location' => 'https://feeds.example/manual.xml',
			),
		);
		if ( 'reuse' === $mode ) {
			$remote[] = $owned;
		}
		$requests       = array();
		$posts          = array();
		$legacy         = in_array( $mode, array( 'recover_legacy', 'recover_legacy_positive' ), true );
		$recovery       = $legacy || in_array( $mode, array( 'recover', 'recover_after_success' ), true );
		$upstream_fails = $recovery;
		$error_caches   = array();
		$observe_cache  = function ( $key, $value, $expiration ) use ( &$error_caches ) {
			if ( '47 - Upstream unavailable.' === $value ) {
				$error_caches[] = array(
					'key'        => $key,
					'expiration' => $expiration,
				);
			}
		};
		add_action( 'set_transient', $observe_cache, 10, 3 );
		$transport = function (
			$response,
			$args,
			$url
		) use (
			&$requests,
			&$posts,
			&$remote,
			&$upstream_fails,
			$created
		) {
			$request    = $args['method'] . ' ' . substr( $url, strlen( APIV5::API_DOMAIN ) );
			$requests[] = $request;
			if ( 'GET /catalogs/feeds?ad_account_id=114141241212' === $request ) {
				return self::response( array( 'items' => $remote ) );
			}
			if ( 'POST /catalogs/feeds?ad_account_id=114141241212' === $request ) {
				$posts[] = json_decode( $args['body'], true );
				if ( $upstream_fails ) {
					return self::response(
						array(
							'code'    => 47,
							'message' => 'Upstream unavailable.',
						),
						500
					);
				}
				$remote[] = $created;
				return self::response( $created, 201 );
			}
			if ( 'DELETE /catalogs/feeds/owned-stale?ad_account_id=114141241212' === $request ) {
				$remote = array_values(
					array_filter(
						$remote,
						function ( $feed ) {
							return 'owned-stale' !== $feed['id'];
						}
					)
				);
				return self::response( array(), 204 );
			}
			if ( in_array(
				$request,
				array(
					'GET /catalogs/feeds/owned-current/processing_results?ad_account_id=114141241212&page_size=1',
					'GET /catalogs/feeds/created-current/processing_results?ad_account_id=114141241212&page_size=1',
				),
				true
			) ) {
				return self::response( array( 'items' => array() ) );
			}
			return new \WP_Error( 'unexpected-request', 'Unexpected registration request.' );
		};
		add_filter( 'pre_http_request', $transport, 10, 3 );
		try {
			$configs      = LocalFeedConfigs::get_instance();
			$files        = new FeedFileOperations( $configs );
			$registration = new FeedRegistration( $configs, $files );
			$this->assertTrue( $registration->handle_feed_registration() );
			$files->prepare_temporary_files();
			$this->assertTrue( $registration->handle_feed_registration() );
			$this->assertSame( array(), $requests );
			$this->assertFalse( FeedRegistration::get_locally_stored_registered_feed_id() );
			$this->assertFalse( Pinterest_For_Woocommerce::get_data( 'merchant_locale_not_valid' ) );
			$this->assertFalse( Pinterest_For_Woocommerce::get_data( 'merchant_connected_diff_platform' ) );
			$files->add_footer_to_temporary_feed_files();
			$files->rename_temporary_feed_files_to_final();
			$expected_post = array(
				'name'                 => 'Coverage feed',
				'format'               => 'XML',
				'location'             => $location,
				'catalog_type'         => 'RETAIL',
				'default_currency'     => 'USD',
				'default_locale'       => 'en-US',
				'default_country'      => 'US',
				'default_availability' => 'IN_STOCK',
			);
			$start         = time();
			if ( $legacy ) {
				// Historical key for this literal fixture: test an existing zero-delay permanent error.
				set_transient(
					'pinterest-for-woocommerce_request_e454216a4215c3bf0515acc24f1b4cb2',
					'47 - Upstream unavailable.',
					0
				);
				Pinterest_For_Woocommerce::save_data(
					'create_feed_delay',
					'recover_legacy_positive' === $mode ? 120 : 0
				);
			}
			if ( $recovery ) {
				foreach ( array( 500, 425 ) as $code ) {
					$exception = null;
					try {
						$registration->handle_feed_registration();
					} catch ( PinterestApiException $error ) {
						$exception = $error;
					}
					$this->assertInstanceOf( PinterestApiException::class, $exception );
					$this->assertSame( $code, $exception->getCode() );
					$this->assertFalse( FeedRegistration::get_locally_stored_registered_feed_id() );
					$this->assertSame( $next_delay, Pinterest_For_Woocommerce::get_data( 'create_feed_delay' ) );
					$this->assertTrue( Pinterest_For_Woocommerce::is_business_connected() );
					$this->assertSame(
						'114141241212',
						Pinterest_For_Woocommerce::get_setting( 'tracking_advertiser' )
					);
					$this->assertFalse( Pinterest_For_Woocommerce::get_data( 'merchant_locale_not_valid' ) );
					$this->assertFalse( Pinterest_For_Woocommerce::get_data( 'merchant_connected_diff_platform' ) );
					$this->assertCount( 2, $requests );
				}
				$this->assertCount( $legacy ? 2 : 1, $error_caches );
				$error_cache = end( $error_caches );
				$this->assertSame( $ttl, $error_cache['expiration'] );
				$error_key = $error_cache['key'];
				$this->assertSame( '47 - Upstream unavailable.', get_transient( $error_key ) );
				// Simulate the observed TTL having elapsed, using the same boundary with any cache backend.
				delete_transient( $error_key );
				$upstream_fails = false;
			}
			$this->assertTrue( $registration->handle_feed_registration() );
			$end = time();
			$this->assertSame(
				'reuse' === $mode ? 'owned-current' : 'created-current',
				FeedRegistration::get_locally_stored_registered_feed_id()
			);
			$this->assertTrue( Pinterest_For_Woocommerce::is_business_connected() );
			foreach ( $posts as $post ) {
				$schedule = $post['preferred_processing_schedule'];
				unset( $post['preferred_processing_schedule'] );
				$this->assertSame( $expected_post, $post );
				$this->assertSame( 'Etc/UTC', $schedule['timezone'] );
				$this->assertContains(
					$schedule['time'],
					array( gmdate( 'H:i', $start + 300 ), gmdate( 'H:i', $end + 300 ) )
				);
			}
			if ( 'reuse' !== $mode ) {
				$this->assertFalse( Pinterest_For_Woocommerce::get_data( 'create_feed_delay' ) );
			}
			$this->assertFalse(
				APIV5::get_cached_response( 'catalogs/feeds?ad_account_id=114141241212', 'GET', array(), '' )
			);
			$remote[]  = array(
				'id'       => 'manual-later',
				'location' => 'https://feeds.example/later.xml',
			);
			$remaining = array_column( Feeds::get_feeds(), 'id' );
			sort( $remaining );
			$this->assertSame(
				'reuse' === $mode
					? array( 'manual-external', 'manual-later', 'manual-local', 'owned-current' )
					: array( 'created-current', 'manual-external', 'manual-later', 'manual-local' ),
				$remaining
			);
			$this->assertSame( $expected_requests, $requests );
		} finally {
			remove_action( 'set_transient', $observe_cache, 10 );
			remove_filter( 'pre_http_request', $transport, 10 );
			remove_filter( 'pinterest_for_woocommerce_unique_feed_name', $name );
			remove_filter( 'upload_dir', $uploads );
			// Upstream failure also writes WooCommerce logs inside this test's uploads directory.
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
			$filesystem = new \WP_Filesystem_Direct( null );
			$filesystem->delete( $directory, true );
			Pinterest_For_Woocommerce::save_settings( $original_settings );
			Pinterest_For_Woocommerce::save_settings( $original_data, PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );
			update_option( 'woocommerce_default_country', $original_country );
			update_option( 'woocommerce_currency', $original_currency );
			$token_cache->setValue( null, $original_token );
		}
	}

	/** @return array Native registration workflow paths and independent expected requests. */
	public function registration_workflows() {
		$list   = 'GET /catalogs/feeds?ad_account_id=114141241212';
		$create = 'POST /catalogs/feeds?ad_account_id=114141241212';
		$remove = 'DELETE /catalogs/feeds/owned-stale?ad_account_id=114141241212';
		return array(
			'reuse'                                        => array(
				'reuse',
				array(
					$list,
					$remove,
					'GET /catalogs/feeds/owned-current/processing_results?ad_account_id=114141241212&page_size=1',
					$list,
				),
			),
			'create'                                       => array(
				'create',
				array(
					$list,
					$create,
					$list,
					$remove,
					'GET /catalogs/feeds/created-current/processing_results?ad_account_id=114141241212&page_size=1',
					$list,
				),
			),
			'recover'                                      => array(
				'recover',
				array(
					$list,
					$create,
					$create,
					$list,
					$remove,
					'GET /catalogs/feeds/created-current/processing_results?ad_account_id=114141241212&page_size=1',
					$list,
				),
			),
			'recover after success'                        => array(
				'recover_after_success',
				array(
					$list,
					$create,
					$create,
					$list,
					$remove,
					'GET /catalogs/feeds/created-current/processing_results?ad_account_id=114141241212&page_size=1',
					$list,
				),
			),
			'recover permanent legacy cache'               => array(
				'recover_legacy',
				array(
					$list,
					$create,
					$create,
					$list,
					$remove,
					'GET /catalogs/feeds/created-current/processing_results?ad_account_id=114141241212&page_size=1',
					$list,
				),
			),
			'legacy cache after another payload recovered' => array(
				'recover_legacy_positive',
				array(
					$list,
					$create,
					$create,
					$list,
					$remove,
					'GET /catalogs/feeds/created-current/processing_results?ad_account_id=114141241212&page_size=1',
					$list,
				),
				240,
				120,
			),
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

		if ( 'DELETE' === $args['method'] && preg_match( '#catalogs/feeds/([^?]*)#', $url, $matches ) ) {
			self::$deleted_feed_ids[] = $matches[1];
			return self::response( array(), 204 );
		}

		if ( false !== strpos( $url, '/processing_results?' ) ) {
			return self::response( array( 'items' => array() ) );
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
