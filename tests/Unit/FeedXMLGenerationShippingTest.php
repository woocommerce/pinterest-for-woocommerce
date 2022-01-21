<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Feed;

use ReflectionClass;
use ShippingHelpers;
use WC_Product_Variable;
use \WC_Helper_Product;
use \WC_Unit_Test_Case;

use Automattic\WooCommerce\Pinterest\ProductsXmlFeed;

/**
 * Feed XML file shipping column generation test class.
 */
class Pinterest_Test_Shipping_Feed extends WC_Unit_Test_Case {

	// Holds products for the teardown action.
	private $products = array();

	/**
	 * One time setup of the testing environment.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void
	{
		// Normally this would be loaded but not in the test scenario - so lets load it manually.
		include_once 'includes/admin/class-pinterest-for-woocommerce-admin.php';
	}

	/**
	 * Each test requires at least one product so lets prepare it here.
	 *
	 * @return void
	 */
	public function setUp() {
		$this->products[] = WC_Helper_Product::create_simple_product(  true, array( "regular_price" => 15 ) );
	}

	/**
	 * Delete products created for testing. And proceed with the cleanup.
	 *
	 * @return void
	 */
	public function tearDown() {
		foreach( $this->products as $product ) {
			$product->delete( true );
		}
		ShippingHelpers::cleanup();
	}

	/**
	 * Check what happens when the user has no shipping zones defined.
	 *
	 * @group feed
	 * @group shipping
	 */
	public function testNoShippingZones() {
		$xml        = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '', $xml );
	}

	/**
	 * Single shipping zone with free shipping for all destinations.
	 *
	 * @group feed
	 * @group shipping
	 */
	public function testZoneWithFreeShipping() {
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['US', 'country']
			]
		);
		ShippingHelpers::addFreeShipping( $zone );

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '<g:shipping>US::Free shipping:0.00 USD</g:shipping>', $xml );
	}

	/**
	 * Single shipping zone with flat rate shipping.
	 *
	 * @group feed
	 * @group shipping
	 */
	public function testZoneWithFlatRateShipping() {
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
	 * Single shipping zone that has multiple countries and free shipping method.
	 *
	 * @group feed
	 * @group shipping
	 */
	public function testZoneWithMultipleCountriesAndFreeShipping(){
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
	 * Single zone with that has a country with state as location and flat rate shipping.
	 *
	 * @group feed
	 * @group shipping
	 */
	public function testZoneWithSingleCountryAndStateAndFlatRateShipping() {
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
	 * Single shipping zone with a continent and flat rate shipping.
	 *
	 * @group feed
	 * @group shipping
	 */
	public function testZoneWithContinentAndFlatRateShipping() {
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
	 * Zone that has a country that is not supported.
	 *
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
	 * Zone that has a location with a post code.
	 *
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
	 * Zone with free shipping with a minimum required purchase and product that satisfies the condition.
	 *
	 * @group feed
	 * @group shipping
	 */
	public function testFreeShippingWithMinimumSetAndMet() {
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['US', 'country']
			]
		);
		ShippingHelpers::addFreeShippingWithMinimumOrderAmount( $zone, 10 );

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '<g:shipping>US::Free shipping:0.00 USD</g:shipping>', $xml );
	}

	/**
	 * Zone with free shipping with a minimum required purchase and product that does not satisfies the condition.
	 *
	 * @group feed
	 * @group shipping
	 */
	public function testFreeShippingWithMinimumSetAndNotMet() {
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['US', 'country']
			]
		);
		ShippingHelpers::addFreeShippingWithMinimumOrderAmount( $zone, 20 );

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '', $xml );
	}

	/**
	 * Check if we discard Free shipping method that has other requirements ( coupon ) than minimum purchase value.
	 *
	 * @group feed
	 * @group shipping
	 */
	public function testFreeShippingWithSettingsOtherThanMinimumIsDiscarded() {
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['US', 'country']
			]
		);
		ShippingHelpers::addFreeShippingWithCouponRequirement( $zone, 5 );

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '', $xml );
	}

	/**
	 * Flat rate shipping with no class cost.
	 *
	 * @group feed
	 * @group shipping
	 */
	public function testFlatRateShippingWithNoClassCost() {
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['US', 'country']
			]
		);

		/**
		 * Shipping class needs to be defined before we add shipping method.
		 * Without that the shipping class does not process the shipping classes when it is saved.
		 */
		ShippingHelpers::addShippingClass( 'heavy' );
		ShippingHelpers::addFlatRateShippingMethodToZone( $zone, 19, '10 * [qty]' );

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '<g:shipping>US::Flat rate:29.00 USD</g:shipping>', $xml );
	}

	/**
	 * Flat rate shipping with no class cost and no class defined in WC.
	 *
	 * @group feed
	 * @group shipping
	 */
	public function testFlatRateShippingWithClassCostNoClassSet() {
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['US', 'country']
			]
		);

		/**
		 * Shipping class needs to be defined before we add shipping method.
		 * Without that the shipping class does not process the shipping classes when it is saved.
		 */
		$class_id = ShippingHelpers::addShippingClass( 'medium' );
		ShippingHelpers::addFlatRateShippingMethodToZone( $zone, 15, null, array( $class_id => 17 ) );

		// Product has no class set.
		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '<g:shipping>US::Flat rate:15.00 USD</g:shipping>', $xml );
	}

	/**
	 * Flat rate shipping with class cost.
	 *
	 * @group feed
	 * @group shipping
	 */
	public function testFlatRateShippingWithClassCostClassSet() {
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['US', 'country']
			]
		);

		/**
		 * Shipping class needs to be defined before we add shipping method.
		 * Without that the shipping class does not process the shipping classes when it is saved.
		 */
		$class_id = ShippingHelpers::addShippingClass( 'medium' );
		ShippingHelpers::addFlatRateShippingMethodToZone( $zone, 15, null, array( $class_id => 17 ) );

		// Product has the shipping class set.
		$product = end( $this->products ) ;
		$product->set_shipping_class_id( $class_id );
		$product->save();
		$xml = $this->ProductsXmlFeed__get_property_g_shipping( $product );
		$this->assertEquals( '<g:shipping>US::Flat rate:32.00 USD</g:shipping>', $xml );
	}

	/**
	 * No duplicate locations.
	 * The continent NA location includes 'US' so we test if we don't end up with two US entires.
	 *
	 * @group feed
	 * @group shipping
	 *
	 */
	public function testLocationHasNoDuplicateEntries() {
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['NA', 'continent'],
				['US', 'country'],
			]
		);
		ShippingHelpers::addFreeShipping( $zone );

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '<g:shipping>CA::Free shipping:0.00 USD,US::Free shipping:0.00 USD</g:shipping>', $xml );
	}

	/**
	 * Mixing of country and the same country with state.
	 *
	 * @group feed
	 * @group shipping
	 */
	public function testMixedStateAndCountryLocation() {
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['US:CA', 'state'],
				['US', 'country'],
			]
		);
		ShippingHelpers::addFreeShipping( $zone );

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '<g:shipping>US::Free shipping:0.00 USD,US:CA:Free shipping:0.00 USD</g:shipping>', $xml );
	}

	/**
	 * Single zone, variable product and free shipping with minimum order value.
	 *
	 * @group feed
	 * @group shipping
	 */
	public function testFreeShippingVariableProductWithMinimumOrder() {
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['US', 'country'],
			]
		);
		ShippingHelpers::addFreeShippingWithMinimumOrderAmount( $zone, 12 );

		// By passing manually created Variable Product the create_variation_product will add children to it.
		$product           = new WC_Product_Variable();
		$variation_product = WC_Helper_Product::create_variation_product( $product );

		// create_variation_product creates multiple children, picking first one's cost is 10 ( per create_variation_product )
		$child_id_0    = $variation_product->get_children()[0];
		$child_product = wc_get_product( $child_id_0 );
		$xml = $this->ProductsXmlFeed__get_property_g_shipping( $child_product );
		$this->assertEquals( '', $xml );

		// create_variation_product creates multiple children, picking first one's cost is 15 ( per create_variation_product )
		$child_id_1    = $variation_product->get_children()[1];
		$child_product = wc_get_product( $child_id_1 );
		$xml = $this->ProductsXmlFeed__get_property_g_shipping( $child_product );
		$this->assertEquals( '<g:shipping>US::Free shipping:0.00 USD</g:shipping>', $xml );
	}

	/**
	 * Shipping tax calculation.
	 *
	 * @group feed
	 * @group shipping
	 */
	public function testTaxCalculationOnShipping() {
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['US', 'country'],
			]
		);
		ShippingHelpers::addFlatRateShippingMethodToZone( $zone, 10 );

		update_option( 'woocommerce_calc_taxes', 'no' );

		ShippingHelpers::addTaxRate( '', '', '20', '1' );

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '<g:shipping>US::Flat rate:10.00 USD</g:shipping>', $xml );

		// Enable tax calculations.
		update_option( 'woocommerce_calc_taxes', 'yes' );

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '<g:shipping>US::Flat rate:12.00 USD</g:shipping>', $xml );
	}

	/**
	 * Shipping calculation if defined tax rate is not applicable to shipping.
	 *
	 * @group feed
	 * @group shipping
	 * @group shipping_tax
	 */
	public function testTaxCalculationOnShippingTaxRateNotApplicableToShipping() {
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['US', 'country'],
			]
		);

		ShippingHelpers::addFlatRateShippingMethodToZone( $zone, 10 );

		ShippingHelpers::addTaxRate( '', '', '20', '0' );

		update_option( 'woocommerce_calc_taxes', 'no' );

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '<g:shipping>US::Flat rate:10.00 USD</g:shipping>', $xml );

		// Enable tax calculations. We still should see flat rate original cost bc tax rate is not applicable to shipping.
		update_option( 'woocommerce_calc_taxes', 'yes' );

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '<g:shipping>US::Flat rate:10.00 USD</g:shipping>', $xml );
	}

	/**
	 * Tax calculation when tax specifies a country.
	 *
	 * @group feed
	 * @group shipping
	 * @group shipping_tax
	 */
	public function testTaxCalculationsAreOnlyAppliedToMatchingCountries() {
		$zone = ShippingHelpers::createZoneWithLocations(
			[
				['US', 'country'],
				['CA', 'country']
			]
		);
		ShippingHelpers::addFlatRateShippingMethodToZone( $zone, 10 );

		ShippingHelpers::addTaxRate( 'CA', '', '20.0000', '1' );

		update_option( 'woocommerce_calc_taxes', 'yes' );

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( end( $this->products ) );
		$this->assertEquals( '<g:shipping>US::Flat rate:10.00 USD,CA::Flat rate:12.00 USD</g:shipping>', $xml );
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

