<?php
/**
 * Persisted historical state and storefront data contracts.
 *
 * @package Automattic\WooCommerce\Pinterest\Tests
 */

namespace Automattic\WooCommerce\Pinterest\Tests\Integration;

use Automattic\WooCommerce\Pinterest\Admin\Admin;
use Automattic\WooCommerce\Pinterest\Admin\Product\Attributes\AttributesTab;
use Automattic\WooCommerce\Pinterest\Crypto;
use Automattic\WooCommerce\Pinterest\Logger;
use Automattic\WooCommerce\Pinterest\PluginUpdate;
use Automattic\WooCommerce\Pinterest\ProductFeedStatus;
use Automattic\WooCommerce\Pinterest\ProductsXmlFeed;
use Automattic\WooCommerce\Pinterest\Product\Attributes\AttributeManager;
use Automattic\WooCommerce\Pinterest\Tracking;
use Automattic\WooCommerce\Pinterest\Tracking\Conversions;
use Automattic\WooCommerce\Pinterest\Tracking\Data\User;
use Pinterest_For_Woocommerce as Plugin;
use WP_UnitTestCase;

/** Real migrations, save hooks and conversion payloads after database reload. */
class SavedDataTest extends WP_UnitTestCase {
	/**
	 * HTTP requests observed at the external boundary.
	 *
	 * @var array
	 */
	private $requests = array();
	/**
	 * Captured conversion payloads.
	 *
	 * @var array
	 */
	private $events = array();
	/**
	 * Requests without declared responses.
	 *
	 * @var array
	 */
	private $unexpected = array();
	/**
	 * Merchant-managed remote catalog fixtures.
	 *
	 * @var array
	 */
	private $merchant_feeds = array();
	/**
	 * Logger owned by the calling suite.
	 *
	 * @var mixed
	 */
	private $previous_logger;
	/**
	 * Feed state before this fixture.
	 *
	 * @var array
	 */
	private $previous_feed_state;

	/** Set up isolated HTTP responses and snapshot static state. */
	public function set_up() {
		parent::set_up();
		$this->previous_logger = Logger::$logger;
		Logger::$logger        = null;
		$state                 = new \ReflectionProperty( ProductFeedStatus::class, 'state' );
		$state->setAccessible( true );
		$this->previous_feed_state = $state->getValue();
		$this->requests            = array();
		$this->events              = array();
		add_filter( 'pre_http_request', array( $this, 'respond' ), 10, 3 );
	}

	/** Restore the caller's state after the database transaction rolls back. */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'respond' ), 10 );
		Logger::$logger = $this->previous_logger;
		$state          = new \ReflectionProperty( ProductFeedStatus::class, 'state' );
		$state->setAccessible( true );
		$state->setValue( null, $this->previous_feed_state );
		parent::tear_down();
		Plugin::get_settings( true );
		Plugin::get_settings( true, PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );
		$this->assertSame( array(), $this->unexpected, 'All external requests must have a declared response.' );
	}

	/**
	 * Deterministic responses at the HTTP boundary; migrations remain real.
	 *
	 * @param mixed  $response Short-circuited response.
	 * @param array  $args Request arguments.
	 * @param string $url Request URL.
	 * @return array|\WP_Error
	 */
	public function respond( $response, $args, $url ) {
		$this->requests[] = array( $args['method'], $url );
		$path             = wp_parse_url( $url, PHP_URL_PATH );
		$responses        = array(
			'POST /v5/oauth/commerce_integrations/token/exchange/' => array(
				'status' => 'success',
				'data'   => array(
					'access_token'             => 'local-new-access',
					'refresh_token'            => 'local-new-refresh',
					'expires_in'               => 3600,
					'refresh_token_expires_in' => 7200,
					'scopes'                   => 'ads:read',
				),
			),
			'POST /v5/integrations/commerce' => array(
				'id'                   => 'integration-123',
				'external_business_id' => 'local-integration',
			),
			'GET /v5/user_account'           => array(
				'id'           => 'merchant-123',
				'username'     => 'local-merchant',
				'account_type' => 'BUSINESS',
			),
			'GET /v5/user_account/websites'  => array( 'items' => array() ),
			'GET /v5/catalogs/feeds'         => array( 'items' => $this->merchant_feeds ),
		);
		if ( 'POST' === $args['method'] && '/v5/ad_accounts/merchant-advertiser/events' === $path ) {
			$this->events[] = json_decode( $args['body'], true )['data'][0];
			$body           = array(
				'num_events_received'  => 1,
				'num_events_processed' => 1,
				'events'               => array( array( 'status' => 'processed' ) ),
			);
		} elseif ( isset( $responses[ $args['method'] . ' ' . $path ] ) ) {
			$body = $responses[ $args['method'] . ' ' . $path ];
		} else {
			$this->unexpected[] = array( $args['method'], $url );
			return new \WP_Error( 'unexpected_test_http', 'Unexpected test request: ' . $args['method'] . ' ' . $url );
		}
		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => wp_json_encode( $body ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
		);
	}

	/** Upgrade a documented pre-v5 token and transient feed-state fixture. */
	public function test_real_139_upgrade_preserves_historical_state_twice() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local versioned fixture.
		$fixture              = json_decode( file_get_contents( __DIR__ . '/data/settings-1.3.9.json' ), true );
		$this->merchant_feeds = $fixture['merchant_feeds'];
		Plugin::save_settings( $fixture['settings'] );
		Plugin::save_settings( $fixture['data'], PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );
		Plugin::save_data( 'token', array( 'access_token' => Crypto::encrypt( 'local-v3-access' ) ) );
		Plugin::set_api_version( 'v3' );
		update_option( PluginUpdate::PLUGIN_UPDATE_VERSION_OPTION, $fixture['source_version'] );
		foreach ( $fixture['feed_status'] as $key => $value ) {
			set_transient( ProductFeedStatus::PINTEREST_FOR_WOOCOMMERCE_FEEDS_DATA_PREFIX . $key, $value );
		}
		$update = new PluginUpdate();
		$update->maybe_update();
		$this->assertSame( 'v5', Plugin::get_api_version() );
		$token = Plugin::get_data( 'token_data', true );
		$this->assertSame( 'local-new-access', Crypto::decrypt( $token['access_token'] ) );
		$this->assertSame( 'local-new-refresh', Crypto::decrypt( $token['refresh_token'] ) );
		$this->assertNotSame( 'local-new-access', $token['access_token'] );
		$settings = Plugin::get_settings( true );
		$this->assertArrayHasKey( 'track_conversions_capi', $settings );
		$this->assertFalse( $settings['track_conversions_capi'] );
		$this->assertSame( $fixture['settings']['merchant_extension_setting'], Plugin::get_setting( 'merchant_extension_setting', true ) );
		$this->assertSame( $fixture['data']['local_feed_ids'], Plugin::get_data( 'local_feed_ids', true ) );
		$this->assertSame( $fixture['data']['merchant_extension_data'], Plugin::get_data( 'merchant_extension_data', true ) );
		foreach ( $fixture['feed_status'] as $key => $value ) {
			$this->assertEquals( $value, get_option( ProductFeedStatus::PINTEREST_FOR_WOOCOMMERCE_FEEDS_DATA_PREFIX . $key ) );
			$this->assertFalse( get_transient( ProductFeedStatus::PINTEREST_FOR_WOOCOMMERCE_FEEDS_DATA_PREFIX . $key ) );
		}
		$this->assertSame( $fixture['merchant_feeds'], \Automattic\WooCommerce\Pinterest\API\APIV5::get_feeds( 'merchant-advertiser' )['items'] );
		$saved = array( get_option( PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME ), get_option( PINTEREST_FOR_WOOCOMMERCE_DATA_NAME ), $this->requests );
		// Re-enter the public upgrade path with the saved version gate intact.
		$update->maybe_update();
		$this->assertSame( $saved, array( get_option( PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME ), get_option( PINTEREST_FOR_WOOCOMMERCE_DATA_NAME ), $this->requests ) );
		foreach ( $fixture['feed_status'] as $key => $value ) {
			$this->assertEquals( $value, get_option( ProductFeedStatus::PINTEREST_FOR_WOOCOMMERCE_FEEDS_DATA_PREFIX . $key ) );
		}
		$this->assertSame( PINTEREST_FOR_WOOCOMMERCE_VERSION, get_option( PluginUpdate::PLUGIN_UPDATE_VERSION_OPTION ) );
		$this->assertNotEmpty( $this->requests );
		foreach ( $this->requests as $request ) {
			if ( false !== strpos( $request[1], '/catalogs/feeds' ) ) {
				$this->assertSame( 'GET', $request[0], 'An upgrade must not modify merchant-managed feeds.' );
			}
		}
	}

	/** The pre-1.0.10 single feed ID moves to the country map once. */
	public function test_109_local_feed_id_moves_without_losing_unrelated_data() {
		Plugin::save_settings( array() );
		Plugin::save_settings(
			array(
				'local_feed_id'   => 'historical-feed',
				'merchant_marker' => 'keep',
			),
			PINTEREST_FOR_WOOCOMMERCE_DATA_NAME
		);
		update_option( PluginUpdate::PLUGIN_UPDATE_VERSION_OPTION, '1.0.9' );
		$update = new PluginUpdate();
		$update->maybe_update();
		$this->assertSame( array( Plugin::get_base_country() => 'historical-feed' ), Plugin::get_data( 'local_feed_ids', true ) );
		$this->assertNull( Plugin::get_data( 'local_feed_id', true ) );
		$this->assertSame( 'keep', Plugin::get_data( 'merchant_marker', true ) );
		$saved = get_option( PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );
		// Re-enter the public upgrade path with the saved version gate intact.
		$update->maybe_update();
		$this->assertSame( $saved, get_option( PINTEREST_FOR_WOOCOMMERCE_DATA_NAME ) );
		$this->assertSame( array(), $this->requests );
	}

	/** A real WooCommerce save submits and persists the attribute form. */
	public function test_normal_product_save_preserves_feed_attributes() {
		$tab = new AttributesTab( $this->createMock( Admin::class ) );
		$tab->register();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Restore the test request.
		$previous = $_POST;
		try {
			$_POST['pinterest_attributes'] = wp_slash(
				array(
					'condition'               => ' used ',
					'google_product_category' => ' 2271 ',
					'unrelated'               => 'ignored',
				)
			);
			$product                       = new \WC_Product_Simple();
			$product->set_name( 'Saved attribute fixture' );
			$product->set_regular_price( '19.50' );
			$product->update_meta_data( 'merchant_metadata', 'keep' );
			$product->save();
		} finally {
			$_POST = $previous;
		}
		$saved = new \WC_Product_Simple( $product->get_id() );
		$this->assertSame(
			array(
				'condition'               => 'used',
				'google_product_category' => '2271',
			),
			AttributeManager::instance()->get_all_values( $saved )
		);
		$this->assertSame( 'keep', $saved->get_meta( 'merchant_metadata' ) );
		$xml = ProductsXmlFeed::get_xml_item( $saved, 'US' );
		$this->assertStringContainsString( '<g:condition>used</g:condition>', $xml );
		$this->assertStringContainsString( '<g:google_product_category>2271</g:google_product_category>', $xml );
	}

	/**
	 * Conversion values come from persisted discounted line items, excluding shipping.
	 *
	 * @dataProvider order_storage
	 * @param string $storage HPOS option value.
	 */
	public function test_conversion_uses_reloaded_order_values( $storage ) {
		update_option( 'woocommerce_custom_orders_table_enabled', $storage );
		update_option( 'woocommerce_currency', 'EUR' );
		Plugin::save_settings(
			array(
				'tracking_advertiser'    => 'merchant-advertiser',
				'track_conversions_capi' => true,
			)
		);
		$product = new \WC_Product_Simple();
		$product->set_name( 'Persisted conversion product' );
		$product->set_regular_price( '24.00' );
		$product->save();
		$order = wc_create_order();
		$order->set_currency( 'EUR' );
		$order->add_product(
			$product,
			2,
			array(
				'subtotal' => 48,
				'total'    => 39,
			)
		);
		$shipping = new \WC_Order_Item_Shipping();
		$shipping->set_total( '7.00' );
		$order->add_item( $shipping );
		$order->calculate_totals( false );
		$order->save();
		$id = $order->get_id();
		unset( $order );
		$this->assertSame( 'yes' === $storage, \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() );
		$this->assertSame( '46.00', wc_get_order( $id )->get_total() );
		$tracking = new Tracking( array( new Conversions( new User( '127.0.0.1', 'Local integration test' ) ) ) );
		$tracking->handle_checkout( $id );
		$this->assertCount( 1, $this->events );
		$this->assertSame( 'checkout_' . $id, $this->events[0]['event_id'] );
		$this->assertSame(
			array(
				'order_id'    => (string) $id,
				'currency'    => 'EUR',
				'value'       => '39.00',
				'content_ids' => array( (string) $product->get_id() ),
				'contents'    => array(
					array(
						'id'         => (string) $product->get_id(),
						'item_price' => '19.5',
						'quantity'   => 2,
					),
				),
				'num_items'   => 2,
			),
			$this->events[0]['custom_data']
		);
	}

	/**
	 * Both supported WooCommerce order stores.
	 *
	 * @return array
	 */
	public function order_storage() {
		return array(
			'legacy' => array( 'no' ),
			'HPOS'   => array( 'yes' ),
		);
	}
}
