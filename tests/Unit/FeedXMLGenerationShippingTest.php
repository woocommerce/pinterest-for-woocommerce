<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Feed;

use ReflectionClass;
use \WC_Cache_Helper;
use \WC_Helper_Product;
use \WC_Shipping_Rate;
use \WC_Shipping_Zone;
use \WC_Shipping_Zones;
use \WC_Unit_Test_Case;

use Automattic\WooCommerce\Pinterest\ProductsXmlFeed;

/**
 * Feed file shipping generation testing class.
 */
class Pinterest_Test_Shipping_Feed extends WC_Unit_Test_Case {

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
		$shipping = ( new ReflectionClass( ProductsXmlFeed::class ) )->getProperty( 'shipping' );
		$shipping->setAccessible( 'true' );
		$shipping->setValue( null );
	}

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
	public function testPropertyShippingWithFreeShippingXML() {
		$product    = WC_Helper_Product::create_simple_product();

		// US zone.
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'US' );
		$zone->set_zone_order( 4 );
		$zone->add_location( 'US', 'country' );
		$zone->add_shipping_method( 'free_shipping' );
		$zone->save();

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( $product );
		$this->assertEquals( '<g:shipping>US::Free shipping:0.00 USD</g:shipping>', $xml );
	}

	/**
	 * @group feed
	 * @group shipping
	 */
	public function testPropertyShippingWithFlatRateShippingXML() {
		$product    = WC_Helper_Product::create_simple_product();

		// US zone.
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'US' );
		$zone->set_zone_order( 4 );
		$zone->add_location( 'US', 'country' );
		$instance_id = $zone->add_shipping_method( 'flat_rate' );
		$zone->save();

		$shipping_method = WC_Shipping_Zones::get_shipping_method( $instance_id );

		$shipping_method_configuration = array(
			'woocommerce_flat_rate_title'         => 'Flat rate',
			'woocommerce_flat_rate_tax_status'    => 'taxable',
			'woocommerce_flat_rate_cost'          => '15',
			'woocommerce_flat_rate_class_cost_19' => '',
			'woocommerce_flat_rate_no_class_cost' => '',
			'woocommerce_flat_rate_type'          => 'class',
			'instance_id'                         => $instance_id
		);

		$shipping_method->set_post_data( $shipping_method_configuration );

		// Cheat process_admin_options that this is a shipping method save request.
		$_REQUEST['instance_id'] = $instance_id;
		$shipping_method->process_admin_options();

		WC_Cache_Helper::invalidate_cache_group( 'shipping_zones' );

		$xml = $this->ProductsXmlFeed__get_property_g_shipping( $product );
		$this->assertEquals( '<g:shipping>US::Flat rate:15.00 USD</g:shipping>', $xml );
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

