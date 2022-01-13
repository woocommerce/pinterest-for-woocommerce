<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Feed;

use ReflectionClass;
use \WC_Helper_Product;
use \WC_Unit_Test_Case;

use Automattic\WooCommerce\Pinterest\ProductsXmlFeed;

/**
 * Feed file shipping generation testing class.
 */
class Pinterest_Test_Shipping_Feed extends WC_Unit_Test_Case {

	/**
	 * @group feed
	 * @group shipping
	 */
	public function testPropertyShippingNoShippingZonesXML() {
		$product    = WC_Helper_Product::create_simple_product();
		$xml        = $this->ProductsXmlFeed__get_property_g_shipping( $product );
		$this->assertEquals( '', $xml );
	}

	/**
	 * @group feed
	 * @group shipping
	 */
	public function testPropertyShippingWithShippingZonesXML() {
		$product    = WC_Helper_Product::create_simple_product();
		$xml        = $this->ProductsXmlFeed__get_property_g_shipping( $product );
		$this->assertEquals( '<g:shipping>new</g:shipping>', $xml );
	}

	/**
	 * Helper function for extracting the static private members of the ProductsXmlFeed class.
	 * Gets the property method then just pass the product and voila.
	 *
	 * @param string $attribute
	 * @return function
	 */
	private function ProductsXmlFeed__get_property_g_shipping() {
		$$method = ( new ReflectionClass( ProductsXmlFeed::class ) )->getMethod( 'get_property_g_shipping' );
		$method->setAccessible( true );

		return $method->invoke( null, $product, 'g:shipping');
	}

}

