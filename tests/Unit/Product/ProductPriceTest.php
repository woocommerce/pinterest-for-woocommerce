<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Product;

use Automattic\WooCommerce\Pinterest\Product\ProductPrice;
use CompositeProductStub;
use WC_Helper_Product;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Product prices sent to Pinterest.
 *
 * @version x.x.x
 */
class ProductPriceTest extends WP_UnitTestCase {

	/**
	 * Empty the customer cart between tests.
	 */
	public function tearDown(): void {
		WC()->cart->empty_cart();
		parent::tearDown();
	}

	/**
	 * A simple product keeps its WooCommerce display prices.
	 */
	public function test_simple_product_uses_display_prices() {
		$product = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => '10' ) );

		$this->assertEquals( 10, ProductPrice::get_price( $product, ProductPrice::CONTEXT_RICH_PINS ) );
		$this->assertEquals( 10, ProductPrice::get_regular_price( $product, ProductPrice::CONTEXT_FEED ) );
	}

	/**
	 * A composite with an empty container price uses its minimum composite prices.
	 */
	public function test_composite_uses_minimum_composite_prices() {
		$product = CompositeProductStub::create( '20', '25' );

		$this->assertTrue( ProductPrice::is_composite( $product ) );
		$this->assertEquals( 20, ProductPrice::get_price( $product, ProductPrice::CONTEXT_PAGE_VISIT ) );
		$this->assertEquals( 25, ProductPrice::get_regular_price( $product, ProductPrice::CONTEXT_FEED ) );
		$this->assertEquals( 20, ProductPrice::get_shipping_price( $product ) );
	}

	/**
	 * A composite without a composite price falls back to its empty container price.
	 */
	public function test_composite_without_composite_price_uses_container_price() {
		$product = CompositeProductStub::create( '', '' );

		$this->assertEmpty( (float) ProductPrice::get_price( $product, ProductPrice::CONTEXT_RICH_PINS ) );
		$this->assertEmpty( (float) ProductPrice::get_regular_price( $product, ProductPrice::CONTEXT_FEED ) );
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
	 * A composite container in the cart is priced with its components, per container.
	 */
	public function test_add_to_cart_price_of_composite_container_includes_components() {
		$container_key = $this->add_composite_to_cart( '5', 2, array( array( '10', 2 ), array( '3', 4 ) ) );
		$container     = WC()->cart->get_cart_item( $container_key )['data'];

		// 5 + 10 * 2 / 2 + 3 * 4 / 2.
		$this->assertEquals( 21, ProductPrice::get_add_to_cart_price( $container, $container_key ) );
	}

	/**
	 * A component listed by the container but no longer in the cart is skipped.
	 */
	public function test_add_to_cart_price_skips_missing_component() {
		$container_key = $this->add_composite_to_cart( '5', 1, array( array( '10', 1 ) ) );
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

	/**
	 * Adds a container and its components to the cart, linked the way Composite Products links them.
	 *
	 * @param string $container_price    Container price.
	 * @param int    $container_quantity Container quantity.
	 * @param array  $components         List of [ price, quantity ] for each component.
	 *
	 * @return string Container cart item key.
	 */
	private function add_composite_to_cart( $container_price, $container_quantity, array $components ) {
		$container     = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => $container_price ) );
		$container_key = WC()->cart->add_to_cart( $container->get_id(), $container_quantity );

		$children = array();
		foreach ( $components as list( $price, $quantity ) ) {
			$component  = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => $price ) );
			$children[] = WC()->cart->add_to_cart( $component->get_id(), $quantity );
		}

		WC()->cart->cart_contents[ $container_key ]['composite_data']     = array( 'component' => array() );
		WC()->cart->cart_contents[ $container_key ]['composite_children'] = $children;

		return $container_key;
	}
}
