<?php
/**
 * Seed/reset only a dedicated local browser store. Invoked through WP-CLI.
 *
 * @package Pinterest_For_WooCommerce
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'PINTEREST_E2E' ) || ! PINTEREST_E2E ) {
	throw new RuntimeException( 'This seed requires the PINTEREST_E2E local test store.' );
}
use Pinterest_For_Woocommerce as Plugin;
// phpcs:disable WordPress.DB.SlowDBQuery -- Only suite-owned records in the disposable test store.
foreach ( wc_get_orders(
	array(
		'limit'      => -1,
		'status'     => array_merge( array_keys( wc_get_order_statuses() ), array( 'trash', 'checkout-draft' ) ),
		'meta_key'   => '_pinterest_e2e',
		'meta_value' => 'yes',
	)
) as $fixture_order ) {
	$fixture_order->delete( true );
}
// Cached responses in this dedicated store come exclusively from the local API fixture.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Discover plugin response-cache keys before deleting through the public transient API.
$cache_names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_' . PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_request_' ) . '%' ) );
foreach ( $cache_names as $cache_name ) {
	delete_transient( substr( $cache_name, strlen( '_transient_' ) ) );
}
delete_transient( PINTEREST_FOR_WOOCOMMERCE_CONNECT_NONCE );
$previous = get_option( 'pinterest_e2e_fixture', array() );
if ( ! empty( $previous['tax'] ) ) {
	WC_Tax::_delete_tax_rate( $previous['tax'] );
}
if ( ! empty( $previous['shipping_zone'] ) ) {
	$zone = WC_Shipping_Zones::get_zone( $previous['shipping_zone'] );
	if ( $zone ) {
		$zone->delete();
	}
}
foreach ( $previous['posts'] ?? array() as $fixture_id ) {
	wp_delete_post( $fixture_id, true );
}
$journal = array(
	'posts'    => array(),
	'products' => array(),
);
update_option( 'pinterest_e2e_fixture', $journal );
WC_Install::create_pages();
wp_update_post(
	array(
		'ID'           => get_option( 'woocommerce_cart_page_id' ),
		'post_content' => '[woocommerce_cart]',
	)
);
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_currency', 'EUR' );
update_option( 'woocommerce_default_country', 'US:CA' );
update_option( 'woocommerce_calc_taxes', 'yes' );
update_option( 'woocommerce_prices_include_tax', 'no' );
update_option( 'woocommerce_tax_based_on', 'base' );
update_option( 'woocommerce_tax_display_cart', 'excl' );
update_option( 'woocommerce_ship_to_destination', 'billing' );
$journal['tax'] = WC_Tax::_insert_tax_rate(
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
update_option( 'pinterest_e2e_fixture', $journal );
$zone = new WC_Shipping_Zone();
$zone->set_zone_name( 'Pinterest browser shipping' );
$zone->add_location( 'US', 'country' );
$zone->save();
$journal['shipping_zone'] = $zone->get_id();
update_option( 'pinterest_e2e_fixture', $journal );
$instance = $zone->add_shipping_method( 'flat_rate' );
update_option(
	'woocommerce_flat_rate_' . $instance . '_settings',
	array(
		'title'      => 'Browser shipping',
		'cost'       => '7',
		'tax_status' => 'none',
	)
);
update_option( 'woocommerce_enable_guest_checkout', 'yes' );
update_option(
	'woocommerce_cod_settings',
	array(
		'enabled'            => 'yes',
		'enable_for_virtual' => 'yes',
	)
);
update_option( 'woocommerce_onboarding_profile', array( 'completed' => true ) );
$customer    = get_user_by( 'login', 'pinterest_customer' );
$customer_id = $customer ? $customer->ID : wp_create_user( 'pinterest_customer', 'pinterest-test-password', 'pinterest-customer@example.test' );
( new WP_User( $customer_id ) )->set_role( 'customer' );
delete_user_meta( $customer_id, '_woocommerce_persistent_cart_' . get_current_blog_id() );
( new WC_Session_Handler() )->delete_session( $customer_id );
delete_transient( 'pinterest_for_woocommerce_async_events_' . md5( $customer_id ) );
foreach ( array_keys( ( new WC_Customer( $customer_id ) )->get_billing() ) as $key ) {
	delete_user_meta( $customer_id, 'billing_' . $key ); }
$product = new WC_Product_Simple();
$product->set_name( 'Pinterest browser product' );
$product->set_status( 'publish' );
$product->set_regular_price( 24 );
$product->set_virtual( true );
$product->save();
$journal['posts'][] = $product->get_id();
update_option( 'pinterest_e2e_fixture', $journal );
$coupon = new WC_Coupon();
$coupon->set_code( 'pinterest-browser-discount' );
$coupon->set_discount_type( 'fixed_cart' );
$coupon->set_amount( 9 );
$coupon->save();
$journal['posts'][] = $coupon->get_id();
update_option( 'pinterest_e2e_fixture', $journal );
$fixture_posts = array( $product->get_id(), $coupon->get_id() );
$fixture_pages = array();
foreach ( array(
	'classic' => '[woocommerce_checkout]',
	'block'   => '<!-- wp:woocommerce/checkout --><div class="wp-block-woocommerce-checkout alignwide wc-block-checkout is-loading"><!-- wp:woocommerce/checkout-fields-block --><div class="wp-block-woocommerce-checkout-fields-block"><!-- wp:woocommerce/checkout-contact-information-block /--><!-- wp:woocommerce/checkout-billing-address-block /--><!-- wp:woocommerce/checkout-payment-block /--><!-- wp:woocommerce/checkout-actions-block /--></div><!-- /wp:woocommerce/checkout-fields-block --><!-- wp:woocommerce/checkout-totals-block --><div class="wp-block-woocommerce-checkout-totals-block"><!-- wp:woocommerce/checkout-order-summary-block /--></div><!-- /wp:woocommerce/checkout-totals-block --></div><!-- /wp:woocommerce/checkout -->',
) as $fixture_mode => $content ) {
	$fixture_pages[ $fixture_mode ] = wp_insert_post(
		array(
			'post_title'   => 'Pinterest checkout ' . $fixture_mode,
			'post_name'    => 'pinterest-checkout-' . $fixture_mode,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => $content,
		)
	);
	$fixture_posts[]                = $fixture_pages[ $fixture_mode ];
	$journal['posts'][]             = $fixture_pages[ $fixture_mode ];
	update_option( 'pinterest_e2e_fixture', $journal );
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
	'tax'           => $journal['tax'],
	'shipping_zone' => $journal['shipping_zone'],
	'posts'         => $fixture_posts,
	'product'       => $product->get_id(),
	'customer'      => $customer_id,
	'pages'         => $fixture_pages,
);
update_option( 'pinterest_e2e_fixture', $fixture );
WP_CLI::line( wp_json_encode( $fixture ) );
