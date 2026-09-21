<?php
/**
 * Owned store data and observations for browser journeys.
 *
 * @package Pinterest_For_WooCommerce
 */

namespace Automattic\WooCommerce\Pinterest\Tests\Browser;

use Pinterest_For_Woocommerce as Plugin;

/**
 * Expose a small set of named operations to the Playwright data adapter.
 */
class StoreFixture {

	/**
	 * Dispatch only the operations used by the browser fixtures.
	 *
	 * @param string $command Operation name.
	 * @param array  $options Operation arguments.
	 * @return mixed
	 */
	public function run( $command, $options = array() ) {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'PINTEREST_E2E' ) || ! PINTEREST_E2E ) {
			throw new \RuntimeException( 'Browser fixtures require WP-CLI and the PINTEREST_E2E test store.' );
		}

		switch ( $command ) {
			case 'seed':
				return $this->seed();
			case 'cleanup':
				$this->cleanup();
				return true;
			case 'prepare-checkout':
				return $this->prepare_checkout( $options );
			case 'allow-connection':
				update_option( 'pinterest_e2e_connect_fail', 0 );
				return true;
			case 'connection-state':
				return $this->connection_state();
			case 'orders':
				return $this->orders();
			case 'events':
				return get_option( 'pinterest_e2e_events', array() );
			case 'unexpected-http':
				return get_option( 'pinterest_e2e_unexpected_http', array() );
			default:
				throw new \InvalidArgumentException( 'Unknown browser fixture command.' );
		}
	}

	/**
	 * Start a deterministic store fixture, recording resources as they are made.
	 *
	 * @return array Product, customer and checkout page IDs.
	 */
	private function seed() {
		$this->cleanup();
		\WC_Install::create_pages();
		// Keep WooCommerce's fresh-install redirect from replacing the tested route.
		delete_transient( '_wc_activation_redirect' );

		$settings = array(
			'woocommerce_coming_soon'           => 'no',
			'woocommerce_currency'              => 'EUR',
			'woocommerce_default_country'       => 'US:CA',
			'woocommerce_calc_taxes'            => 'yes',
			'woocommerce_prices_include_tax'    => 'no',
			'woocommerce_tax_based_on'          => 'base',
			'woocommerce_tax_display_cart'      => 'excl',
			'woocommerce_ship_to_destination'   => 'billing',
			'woocommerce_enable_guest_checkout' => 'yes',
			'woocommerce_cod_settings'          => array(
				'enabled'            => 'yes',
				'enable_for_virtual' => 'yes',
			),
			'woocommerce_onboarding_profile'    => array( 'completed' => true ),
		);
		$journal  = array(
			'posts'   => array(),
			'options' => array(),
		);
		foreach ( array_merge(
			array_keys( $settings ),
			array(
				'woocommerce_checkout_page_id',
				PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME,
				PINTEREST_FOR_WOOCOMMERCE_DATA_NAME,
				PINTEREST_FOR_WOOCOMMERCE_PINTEREST_API_VERSION,
			)
		) as $option ) {
			$journal['options'][ $option ] = get_option( $option, null );
		}
		$cart_id         = (int) get_option( 'woocommerce_cart_page_id' );
		$journal['cart'] = array(
			'id'      => $cart_id,
			'content' => get_post_field( 'post_content', $cart_id ),
		);
		update_option( 'pinterest_e2e_journal', $journal );
		foreach ( $settings as $option => $value ) {
			update_option( $option, $value );
		}
		wp_update_post(
			array(
				'ID'           => $cart_id,
				'post_content' => '[woocommerce_cart]',
			)
		);

		$journal['tax'] = \WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => 'US',
				'tax_rate_state'    => 'CA',
				'tax_rate'          => 10,
				'tax_rate_name'     => 'Browser tax',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 0,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => '',
			)
		);
		update_option( 'pinterest_e2e_journal', $journal );
		$zone = new \WC_Shipping_Zone();
		$zone->set_zone_name( 'Pinterest browser shipping' );
		$zone->add_location( 'US', 'country' );
		$zone->save();
		$journal['shipping_zone'] = $zone->get_id();
		update_option( 'pinterest_e2e_journal', $journal );
		$instance                    = $zone->add_shipping_method( 'flat_rate' );
		$journal['shipping_setting'] = 'woocommerce_flat_rate_' . $instance . '_settings';
		update_option( 'pinterest_e2e_journal', $journal );
		update_option(
			$journal['shipping_setting'],
			array(
				'title'      => 'Browser shipping',
				'cost'       => '7',
				'tax_status' => 'none',
			)
		);

		$customer    = get_user_by( 'login', 'pinterest_customer' );
		$customer_id = $customer ? $customer->ID : wp_create_user( 'pinterest_customer', 'pinterest-test-password', 'pinterest-customer@example.test' );
		if ( is_wp_error( $customer_id ) ) {
			throw new \RuntimeException( esc_html( $customer_id->get_error_message() ) );
		}
		$journal['customer']         = $customer_id;
		$journal['created_customer'] = ! $customer;
		update_option( 'pinterest_e2e_journal', $journal );
		( new \WP_User( $customer_id ) )->set_role( 'customer' );
		$this->clear_customer_session( $customer_id );
		foreach ( array_keys( ( new \WC_Customer( $customer_id ) )->get_billing() ) as $key ) {
			delete_user_meta( $customer_id, 'billing_' . $key );
		}

		$product = new \WC_Product_Simple();
		$product->set_name( 'Pinterest browser product' );
		$product->set_status( 'publish' );
		$product->set_regular_price( 24 );
		$product->set_virtual( true );
		$product->save();
		$journal['posts'][] = $product->get_id();
		update_option( 'pinterest_e2e_journal', $journal );
		$coupon = new \WC_Coupon();
		$coupon->set_code( 'pinterest-browser-discount' );
		$coupon->set_discount_type( 'fixed_cart' );
		$coupon->set_amount( 9 );
		$coupon->save();
		$journal['posts'][] = $coupon->get_id();
		update_option( 'pinterest_e2e_journal', $journal );

		$pages = array();
		foreach ( $this->checkout_content() as $mode => $content ) {
			$pages[ $mode ] = wp_insert_post(
				array(
					'post_title'   => 'Pinterest checkout ' . $mode,
					'post_name'    => 'pinterest-checkout-' . $mode,
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_content' => $content,
				),
				true
			);
			if ( is_wp_error( $pages[ $mode ] ) ) {
				throw new \RuntimeException( esc_html( $pages[ $mode ]->get_error_message() ) );
			}
			$journal['posts'][] = $pages[ $mode ];
			update_option( 'pinterest_e2e_journal', $journal );
		}
		Plugin::save_settings(
			array(
				'enable_debug_logging'       => false,
				'rich_pins_on_products'      => true,
				'merchant_extension_setting' => 'retain',
			)
		);
		Plugin::save_settings( array(), PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );
		Plugin::set_api_version( 'v5' );
		update_option(
			'pinterest_e2e_remote_feeds',
			array(
				array(
					'id'       => 'merchant-feed',
					'name'     => 'Merchant upload',
					'location' => 'https://merchant.example/feed.xml',
				),
			)
		);
		update_option( 'pinterest_e2e_connect_fail', 1 );
		update_option( 'pinterest_e2e_events', array() );
		update_option( 'pinterest_e2e_unexpected_http', array() );
		$fixture = array(
			'product'  => $product->get_id(),
			'customer' => $customer_id,
			'pages'    => $pages,
			'cart'     => $cart_id,
		);
		update_option( 'pinterest_e2e_fixture', $fixture );
		return $fixture;
	}

	/**
	 * Remove suite-owned state and restore the store options changed by seed.
	 *
	 * @return void
	 */
	private function cleanup() {
		$journal = get_option( 'pinterest_e2e_journal', get_option( 'pinterest_e2e_fixture', array() ) );
		// phpcs:disable WordPress.DB.SlowDBQuery -- Only suite-owned orders in this dedicated test store.
		$orders = wc_get_orders(
			array(
				'limit'      => -1,
				'status'     => array_merge( array_keys( wc_get_order_statuses() ), array( 'trash', 'checkout-draft' ) ),
				'meta_key'   => '_pinterest_e2e',
				'meta_value' => 'yes',
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery
		foreach ( $orders as $order ) {
			$order->delete( true );
		}
		if ( ! empty( $journal['tax'] ) ) {
			\WC_Tax::_delete_tax_rate( $journal['tax'] );
		}
		if ( ! empty( $journal['shipping_zone'] ) ) {
			$zone = \WC_Shipping_Zones::get_zone( $journal['shipping_zone'] );
			if ( $zone ) {
				$zone->delete();
			}
		}
		if ( ! empty( $journal['shipping_setting'] ) ) {
			delete_option( $journal['shipping_setting'] );
		}
		foreach ( $journal['options'] ?? array() as $option => $value ) {
			if ( null === $value ) {
				delete_option( $option );
			} else {
				update_option( $option, $value );
			}
		}
		if ( ! empty( $journal['cart'] ) ) {
			wp_update_post(
				array(
					'ID'           => $journal['cart']['id'],
					'post_content' => $journal['cart']['content'],
				)
			);
		}
		foreach ( $journal['posts'] ?? array() as $id ) {
			wp_delete_post( $id, true );
		}
		if ( ! empty( $journal['customer'] ) ) {
			$this->clear_customer_session( $journal['customer'] );
			if ( ! empty( $journal['created_customer'] ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $journal['customer'] );
			}
		}
		$this->clear_response_cache();
		foreach ( array( 'fixture', 'journal', 'connect_fail', 'events', 'unexpected_http', 'remote_feeds' ) as $name ) {
			delete_option( 'pinterest_e2e_' . $name );
		}
	}

	/**
	 * Prepare tracking and select the requested checkout implementation.
	 *
	 * @param array $options Checkout mode and shipping requirement.
	 * @return array Seeded fixture IDs.
	 */
	private function prepare_checkout( $options ) {
		$fixture = get_option( 'pinterest_e2e_fixture', array() );
		$mode    = $options['mode'] ?? '';
		if ( ! in_array( $mode, array( 'classic', 'block' ), true ) || empty( $fixture['pages'][ $mode ] ) ) {
			throw new \InvalidArgumentException( 'Seed the store and select classic or block checkout.' );
		}
		$product = wc_get_product( $fixture['product'] );
		$product->set_virtual( empty( $options['shipping'] ) );
		$product->save();
		Plugin::save_token_data(
			array(
				'access_token'             => 'local-access',
				'refresh_token'            => 'local-refresh',
				'expires_in'               => 3600,
				'refresh_token_expires_in' => 7200,
				'scopes'                   => 'ads:read',
			)
		);
		Plugin::save_settings(
			array(
				'tracking_advertiser'    => 'merchant-advertiser',
				'tracking_tag'           => '1234567890123',
				'track_conversions'      => true,
				'track_conversions_capi' => true,
			)
		);
		update_option( 'woocommerce_checkout_page_id', $fixture['pages'][ $mode ] );
		return $fixture;
	}

	/**
	 * Read the connection settings exercised by the merchant journey.
	 *
	 * @return array
	 */
	private function connection_state() {
		return array(
			'connected'                => Plugin::is_connected(),
			'token'                    => Plugin::get_data( 'token_data', true ),
			'advertiser'               => Plugin::get_setting( 'tracking_advertiser', true ),
			'tag'                      => Plugin::get_setting( 'tracking_tag', true ),
			'debugLogging'             => Plugin::get_setting( 'enable_debug_logging', true ),
			'merchantExtensionSetting' => Plugin::get_setting( 'merchant_extension_setting', true ),
			'integration'              => Plugin::get_data( 'integration_data', true ),
			'remoteFeeds'              => get_option( 'pinterest_e2e_remote_feeds', array() ),
		);
	}

	/**
	 * Read saved order values through WooCommerce CRUD, including HPOS stores.
	 *
	 * @return array
	 */
	private function orders() {
		$results = array();
		// phpcs:disable WordPress.DB.SlowDBQuery -- Only suite-owned orders in this dedicated test store.
		$orders = wc_get_orders(
			array(
				'limit'      => -1,
				'meta_key'   => '_pinterest_e2e',
				'meta_value' => 'yes',
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery
		foreach ( $orders as $order ) {
			$items = array();
			foreach ( $order->get_items() as $item ) {
				$items[] = array(
					'product'  => $item->get_product_id(),
					'quantity' => $item->get_quantity(),
					'total'    => $item->get_total(),
				);
			}
			$results[] = array(
				'id'       => $order->get_id(),
				'currency' => $order->get_currency(),
				'total'    => $order->get_total(),
				'discount' => $order->get_discount_total(),
				'shipping' => $order->get_shipping_total(),
				'tax'      => $order->get_total_tax(),
				'customer' => $order->get_customer_id(),
				'status'   => $order->get_status(),
				'items'    => $items,
			);
		}
		return $results;
	}

	/**
	 * Clear the suite customer's persistent cart and deferred conversion events.
	 *
	 * @param int $customer_id Fixture customer ID.
	 * @return void
	 */
	private function clear_customer_session( $customer_id ) {
		delete_user_meta( $customer_id, '_woocommerce_persistent_cart_' . get_current_blog_id() );
		( new \WC_Session_Handler() )->delete_session( $customer_id );
		delete_transient( 'pinterest_for_woocommerce_async_events_' . md5( $customer_id ) );
	}

	/**
	 * Remove cached fake Pinterest responses and the connection nonce.
	 *
	 * @return void
	 */
	private function clear_response_cache() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Discover response keys, then use the public transient API.
		$cache_names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_' . PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_request_' ) . '%' ) );
		foreach ( $cache_names as $cache_name ) {
			delete_transient( substr( $cache_name, strlen( '_transient_' ) ) );
		}
		delete_transient( PINTEREST_FOR_WOOCOMMERCE_CONNECT_NONCE );
	}

	/**
	 * Checkout markup stays in PHP alongside the pages it seeds.
	 *
	 * @return array
	 */
	private function checkout_content() {
		return array(
			'classic' => '[woocommerce_checkout]',
			'block'   => '<!-- wp:woocommerce/checkout --><div class="wp-block-woocommerce-checkout alignwide wc-block-checkout is-loading"><!-- wp:woocommerce/checkout-fields-block --><div class="wp-block-woocommerce-checkout-fields-block"><!-- wp:woocommerce/checkout-contact-information-block /--><!-- wp:woocommerce/checkout-billing-address-block /--><!-- wp:woocommerce/checkout-payment-block /--><!-- wp:woocommerce/checkout-actions-block /--></div><!-- /wp:woocommerce/checkout-fields-block --><!-- wp:woocommerce/checkout-totals-block --><div class="wp-block-woocommerce-checkout-totals-block"><!-- wp:woocommerce/checkout-order-summary-block /--></div><!-- /wp:woocommerce/checkout-totals-block --></div><!-- /wp:woocommerce/checkout -->',
		);
	}
}
