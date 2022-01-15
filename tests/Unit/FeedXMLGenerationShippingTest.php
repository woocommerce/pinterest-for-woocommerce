<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Feed;

use ReflectionClass;
use ShippingHelpers;
use \WC_Cache_Helper;
use \WC_Helper_Product;
use \WC_Shipping_Zone;
use \WC_Unit_Test_Case;

use Automattic\WooCommerce\Pinterest\ProductsXmlFeed;

/**
 * Feed file shipping generation testing class.
 */
class Pinterest_Test_Shipping_Feed extends WC_Unit_Test_Case {

	// Holds products for the teardown action.
	private $products = array();

	public static function setUpBeforeClass(): void
	{
		// Normally this would be loaded but not in the test scenario - so lets load it manually.
		include_once 'includes/admin/class-pinterest-for-woocommerce-admin.php';
	}

	public function setUp() {
		// Reset WooCommerce shipping data and cache.
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}woocommerce_shipping_zone_methods;" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}woocommerce_shipping_zone_locations;" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}woocommerce_shipping_zones;" );
		WC_Cache_Helper::invalidate_cache_group( 'shipping_zones' );

		// Reset plugin shipping cache.
		$shipping_zones = ( new ReflectionClass( ProductsXmlFeed::class ) )->getProperty( 'shipping_zones' );
		$shipping_zones->setAccessible( 'true' );
		$shipping_zones->setValue( null );

		$this->products[] = WC_Helper_Product::create_simple_product();
	}

	public function teardown() {
		foreach( $this->products as $product ) {
			$product->delete( true );
		}
	}

	/**
	 * @group feed
	 * @group shipping
	 */
	public function testPropertyShippingNoShippingZones() {
		$xml        = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '', $xml );
	}

	/**
	 * @group feed
	 * @group shipping
	 */
	public function testPropertyShippingWithFreeShipping() {
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['US', 'country']
			]
		);
		$zone->add_shipping_method( 'free_shipping' );

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '<g:shipping>US::Free shipping:0.00 USD</g:shipping>', $xml );
	}

	/**
	 * @group feed
	 * @group shipping
	 */
	public function testPropertyShippingWithFlatRateShipping() {
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['US', 'country']
			]
		);
		ShippingHelpers::addFlatRateShippingMethodToZone( $zone );

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '<g:shipping>US::Flat rate:15.00 USD</g:shipping>', $xml );
	}

	/**
	 * @group feed
	 * @group shipping
	 */
	public function testMultipleCountriesNoShipping(){
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['US', 'country'],
				['UK', 'country'],
				['IT', 'country'],
			]
		);
		$zone->add_shipping_method( 'free_shipping' );
		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '<g:shipping>US::Free shipping:0.00 USD,IT::Free shipping:0.00 USD</g:shipping>', $xml );
	}

	/**
	 * @group feed
	 * @group shipping
	 */
	public function testPropertyShippingForStateWithFlatRateShipping() {
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['US:CA', 'state']
			]
		);
		ShippingHelpers::addFlatRateShippingMethodToZone( $zone );

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '<g:shipping>US:CA:Flat rate:15.00 USD</g:shipping>', $xml );
	}

	/**
	 * @group feed
	 * @group shipping
	 */
	public function testPropertyShippingForContinentWithFlatRateShipping() {
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['NA', 'continent']
			]
		);
		ShippingHelpers::addFlatRateShippingMethodToZone( $zone );

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '<g:shipping>CA::Flat rate:15.00 USD,US::Flat rate:15.00 USD</g:shipping>', $xml );
	}

	/**
	 * @group feed
	 * @group shipping
	 */
	public function testNotSupportedCountries() {
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['PK', 'country']
			]
		);
		ShippingHelpers::addFlatRateShippingMethodToZone( $zone );

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '', $xml );
	}

	/**
	 * @group feed
	 * @group shipping
	 */
	public function testIfZonesWithPostCodeAreDiscarded() {
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['US', 'country'],
				['902010', 'postcode'],
			]
		);
		ShippingHelpers::addFlatRateShippingMethodToZone( $zone );

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '', $xml );
	}

	/**
	 * @group feed
	 * @group shipping
	 */
	public function testIfFreeShippingMethodWithAdditionalSettingsIsDiscarded() {
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['US', 'country']
			]
		);
		ShippingHelpers::addFreeShippingWithMinimumOrderAmount( $zone );

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '', $xml );
	}

	/**
	 * Helper function for extracting the static private members of the ProductsXmlFeed class.
	 * Gets the property method then just pass the product and voila.
	 *
	 * @param string $attribute
	 * @return function
	 */
	private function ProductsXmlFeed__get_property_g_shipping( $product ) {
		$method = ( new ReflectionClass( ProductsXmlFeed::class ) )->getMethod( 'get_property_g_shipping' );
		$method->setAccessible( true );

		return $method->invoke( null, $product, 'g:shipping');
	}

}

