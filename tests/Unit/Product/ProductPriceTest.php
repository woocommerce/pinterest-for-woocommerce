<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Product;

use Automattic\WooCommerce\Pinterest\Product\ProductPrice;
use CompositeProductStub;
use WC_Helper_Product;
use WC_Product_Simple;
use WC_Unit_Test_Case;

/**
 * Product prices sent to Pinterest.
 *
 * @version x.x.x
 */
class ProductPriceTest extends WC_Unit_Test_Case {

	/**
	 * A simple product on sale keeps its WooCommerce active and regular display prices.
	 */
	public function test_simple_product_uses_display_prices() {
		$product = WC_Helper_Product::create_simple_product(
			true,
			array(
				'regular_price' => '15',
				'sale_price'    => '10',
			)
		);

		$this->assertEquals( 10, ProductPrice::get_price( $product, ProductPrice::CONTEXT_RICH_PINS ) );
		$this->assertEquals( 15, ProductPrice::get_regular_price( $product, ProductPrice::CONTEXT_FEED ) );
	}


	/**
	 * Composite prices sent to Pinterest are display prices, while the shipping
	 * estimate uses the price as entered.
	 */
	public function test_composite_prices_use_display_prices_and_shipping_uses_entered_price() {
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		update_option( 'woocommerce_tax_display_shop', 'incl' );
		\ShippingHelpers::addTaxRate( '', '', '20.0000' );

		$product = CompositeProductStub::create( '20', '25' );

		$this->assertEquals( 24, ProductPrice::get_price( $product, ProductPrice::CONTEXT_PAGE_VISIT ) );
		$this->assertEquals( 30, ProductPrice::get_regular_price( $product, ProductPrice::CONTEXT_FEED ) );
		$this->assertEquals( 20, ProductPrice::get_shipping_price( $product ) );
	}



	/**
	 * A product of type 'composite' without the composite price methods is not a composite.
	 */
	public function test_composite_type_without_price_methods_is_not_composite() {
		$product = new class() extends WC_Product_Simple {
			/**
			 * Product type.
			 *
			 * @return string
			 */
			public function get_type() {
				return 'composite';
			}
		};
		$product->set_regular_price( '12' );
		$product->set_price( '12' );

		$this->assertFalse( ProductPrice::is_composite( $product ) );
		$this->assertEquals( 12, ProductPrice::get_price( $product, ProductPrice::CONTEXT_RICH_PINS ) );
	}

	/**
	 * The price filter applies to its context and receives the cart item key only for add_to_cart.
	 */
	public function test_price_filter_receives_context_and_cart_item_key() {
		$product  = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => '10' ) );
		$received = array();
		add_filter(
			'pinterest_for_woocommerce_product_price',
			function ( $price, $filtered_product, $context, $cart_item_key ) use ( &$received, $product ) {
				$received[] = array( $filtered_product->get_id() === $product->get_id(), $context, $cart_item_key );
				return 'rich_pins' === $context ? 42 : $price;
			},
			10,
			4
		);

		$cart_item_key = WC()->cart->add_to_cart( $product->get_id(), 1 );

		$this->assertEquals( 42, ProductPrice::get_price( $product, ProductPrice::CONTEXT_RICH_PINS ) );
		$this->assertEquals( 10, ProductPrice::get_price( $product, ProductPrice::CONTEXT_PAGE_VISIT ) );
		$this->assertEquals( 10, ProductPrice::get_add_to_cart_price( $product, $cart_item_key ) );
		$this->assertSame(
			array(
				array( true, 'rich_pins', '' ),
				array( true, 'page_visit', '' ),
				array( true, 'add_to_cart', $cart_item_key ),
			),
			$received
		);
	}

	/**
	 * The regular price filter applies to the regular price.
	 */
	public function test_regular_price_filter_applies() {
		$product = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => '10' ) );
		add_filter(
			'pinterest_for_woocommerce_product_regular_price',
			function ( $price, $filtered_product, $context ) {
				return 'feed' === $context ? '30' : $price;
			},
			10,
			3
		);

		$this->assertEquals( 30, ProductPrice::get_regular_price( $product, ProductPrice::CONTEXT_FEED ) );
		$this->assertEquals( 10, ProductPrice::get_regular_price( $product, ProductPrice::CONTEXT_RICH_PINS ) );
	}

	/**
	 * A filtered value that is not a price is ignored.
	 */
	public function test_invalid_filtered_price_is_ignored() {
		$product = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => '10' ) );
		add_filter( 'pinterest_for_woocommerce_product_price', fn() => array( 'not a price' ) );
		add_filter( 'pinterest_for_woocommerce_product_regular_price', fn() => new \stdClass() );

		$this->assertEquals( 10, ProductPrice::get_price( $product, ProductPrice::CONTEXT_RICH_PINS ) );
		$this->assertEquals( 10, ProductPrice::get_regular_price( $product, ProductPrice::CONTEXT_FEED ) );
	}

	/**
	 * A filtered price in exponent notation is returned as a plain number.
	 */
	public function test_filtered_price_in_exponent_notation_is_a_number() {
		$product = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => '10' ) );
		add_filter( 'pinterest_for_woocommerce_product_price', fn() => '1.5e2' );
		add_filter( 'pinterest_for_woocommerce_product_regular_price', fn() => '1e2' );

		$this->assertSame( 150.0, ProductPrice::get_price( $product, ProductPrice::CONTEXT_RICH_PINS ) );
		$this->assertSame( 100.0, ProductPrice::get_regular_price( $product, ProductPrice::CONTEXT_FEED ) );
	}

	/**
	 * An empty filtered price leaves the price out of Rich Pins but is ignored by events.
	 */
	public function test_empty_filtered_price_is_ignored_by_events() {
		$product       = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => '10' ) );
		$cart_item_key = WC()->cart->add_to_cart( $product->get_id(), 1 );
		add_filter( 'pinterest_for_woocommerce_product_price', fn() => '' );

		$this->assertSame( '', ProductPrice::get_price( $product, ProductPrice::CONTEXT_RICH_PINS ) );
		$this->assertEquals( 10, ProductPrice::get_price( $product, ProductPrice::CONTEXT_PAGE_VISIT ) );
		$this->assertEquals( 10, ProductPrice::get_add_to_cart_price( $product, $cart_item_key ) );
	}


	/**
	 * The items of a bundle used as a component count towards the composite price.
	 */
	public function test_add_to_cart_price_includes_items_of_a_bundle_component() {
		$container_key = CompositeProductStub::add_to_cart( '150', 1, array( array( '100', 1 ) ) );
		$bundle_key    = WC()->cart->cart_contents[ $container_key ]['composite_children'][0];
		$bundled_item  = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => '10' ) );
		$bundled_key   = WC()->cart->add_to_cart( $bundled_item->get_id(), 1 );

		WC()->cart->cart_contents[ $bundle_key ]['bundled_items'] = array( $bundled_key );
		$container = WC()->cart->get_cart_item( $container_key )['data'];

		// 150 + 100 + 10.
		$this->assertEquals( 260, ProductPrice::get_add_to_cart_price( $container, $container_key ) );
	}

	/**
	 * A component listed by the container but no longer in the cart is skipped.
	 */
	public function test_add_to_cart_price_skips_missing_component() {
		$container_key = CompositeProductStub::add_to_cart( '5', 1, array( array( '10', 1 ) ) );
		WC()->cart->cart_contents[ $container_key ]['composite_children'][] = 'missing-key';
		$container = WC()->cart->get_cart_item( $container_key )['data'];

		$this->assertEquals( 15, ProductPrice::get_add_to_cart_price( $container, $container_key ) );
	}

	/**
	 * A plain cart item keeps the product display price.
	 */
	public function test_add_to_cart_price_of_simple_item_is_display_price() {
		$product       = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => '10' ) );
		$cart_item_key = WC()->cart->add_to_cart( $product->get_id(), 3 );

		$this->assertEquals( 10, ProductPrice::get_add_to_cart_price( $product, $cart_item_key ) );
	}
}
