<?php

use Automattic\WooCommerce\Pinterest\Shipping;

/**
 * ShippingHelpers class
 *
 * Utility class with tools that help in setting up the tests environment.
 */
class ShippingHelpers {

	/**
	 * Ids of shipping classes created using the helper functions.
	 * Used by the cleanup method to delete the classes.
	 *
	 * @var array $shipping_classes_ids
	 */
	public static $shipping_classes_ids = array();

	/**
	 * For a given list of locations create a new shipping zone.
	 *
	 * @param array $locations Array of locations for then zone.
	 * @return object Shipping zone.
	 */
	public static function createZoneWithLocations( $locations ) {
		// US zone.
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( array_reduce( $locations, function( $c, $l ) { return $c . '_' . implode( '_', $l ); }, '' ) );
		$zone->set_zone_order( 4 );
		$shipping_locations = array_map(
			function( $location ) {
				return array(
					'code' => $location[0],
					'type' => $location[1],
				);
			},
			$locations
		);
		$zone->set_locations( $shipping_locations );
		$zone->save();
		return $zone;
	}

	/**
	 * Adds a predefined flat rate shipping method to zone.
	 * No additional settings.
	 *
	 * @param WC_Shipping_Zone $zone                   Shipping zone to which add the shipping method.
	 * @param integer          $cost                   Shipping cost.
	 * @param integer|string   $no_class_cost          Cost to add when no classes are specified.
	 * @param array            $shipping_classes_costs Cost associated with shipping classes.
	 * @param string           $tax_status             Is this shipping method taxable or not.
	 * @return void
	 */
	public static function addFlatRateShippingMethodToZone( $zone, $cost = 15, $no_class_cost = null, $shipping_classes_costs = array(), $tax_status = 'taxable' ) {
		$instance_id = $zone->add_shipping_method( 'flat_rate' );
		$shipping_method = WC_Shipping_Zones::get_shipping_method( $instance_id );

		$shipping_method_configuration = array(
			'woocommerce_flat_rate_title'         => 'Flat rate',
			'woocommerce_flat_rate_tax_status'    => $tax_status,
			'woocommerce_flat_rate_cost'          => $cost,
			'woocommerce_flat_rate_type'          => 'class',
			'instance_id'                         => $instance_id
		);

		if ( $no_class_cost ) {
			$shipping_method_configuration['woocommerce_flat_rate_no_class_cost'] = $no_class_cost;
		}

		foreach( $shipping_classes_costs as $id => $cost ) {
			$entry = 'woocommerce_flat_rate_class_cost_' . (string) $id;
			$shipping_method_configuration[ $entry ] = $cost;
		}

		$shipping_method->set_post_data( $shipping_method_configuration );

		// Cheat process_admin_options that this is a shipping method save request.
		$_REQUEST['instance_id'] = $instance_id;
		$shipping_method->process_admin_options();

		WC_Cache_Helper::invalidate_cache_group( 'shipping_zones' );
	}

	/**
	 * Adds a predefined flat rate shipping method to zone. No tax should be applied to the zone.
	 * No additional settings.
	 *
	 * @param WC_Shipping_Zone $zone                   Shipping zone to which add the shipping method.
	 * @param integer          $cost                   Shipping cost.
	 * @param integer|string   $no_class_cost          Cost to add when no classes are specified.
	 * @param array            $shipping_classes_costs Cost associated with shipping classes.
	 * @return void
	 */
	public static function addNonTaxableFlatRate( $zone, $cost = 15, $no_class_cost = null, $shipping_classes_costs = array() ) {
		self::addFlatRateShippingMethodToZone( $zone, $cost, $no_class_cost, $shipping_classes_costs, 'non-taxable' );
	}

	 /**
	  * Free shipping with minimum order value requirement.
	  *
	  * @param WC_Shipping_Zone $zone    Shipping zone to which add the shipping method.
	  * @param integer          $minimum Required minimum value.
	  * @return void
	  */
	public static function addFreeShippingWithMinimumOrderAmount( $zone, $minimum = 10 ) {
		self::addFreeShipping( $zone, 'min_amount', $minimum );
	}

	/**
	 * Free shipping with minimum order or coupon requirement.
	 *
	 * @param WC_Shipping_Zone $zone    Shipping zone to which add the shipping method.
	 * @param integer          $minimum Required minimum value.
	 * @return void
	 */
	public static function addFreeShippingWithCouponRequirement( $zone, $minimum = 10 ) {
		self::addFreeShipping( $zone, 'either', $minimum );
	}

	/**
	 * Adds a flat rate shipping method to zone.
	 *
     * @param WC_Shipping_Zone $zone     Shipping zone to which add the shipping method.
	 * @param string           $requires Requirement.
	 * @param integer          $minimum  Required minimum value.
	 * @return void
	 */
	public static function addFreeShipping( $zone, $requires = null, $minimum = 10 ) {
		$instance_id = $zone->add_shipping_method( 'free_shipping' );
		$shipping_method = WC_Shipping_Zones::get_shipping_method( $instance_id );

		$shipping_method_configuration = array(
			'woocommerce_free_shipping_title'      => 'Free shipping',
			'instance_id'                          => $instance_id
		);

		if ( $requires ) {
			$shipping_method_configuration['woocommerce_free_shipping_requires']   = $requires;
			$shipping_method_configuration['woocommerce_free_shipping_min_amount'] = $minimum;
		}

		$shipping_method->set_post_data( $shipping_method_configuration );

		// Cheat process_admin_options that this is a shipping method save request.
		$_REQUEST['instance_id'] = $instance_id;
		$shipping_method->process_admin_options();

		WC_Cache_Helper::invalidate_cache_group( 'shipping_zones' );
	}

	/**
	 * Add shipping class to the WooCommerce database.
	 *
	 * @param string $name        Shipping class name.
	 * @param string $slug        Shipping class slug.
	 * @param string $description Shipping class description.
	 * @return integer Shipping class term id.
	 */
	public static function addShippingClass( $name, $slug='', $description='' ) {
		if ( empty( $slug ) ) {
			$slug = sanitize_title( $name );
		}

		$args = array(
			'name'        => $name,
			'slug'        => $slug,
			'description' => $description
		);

		$inserted_term = wp_insert_term( $name, 'product_shipping_class', $args );
		static::$shipping_classes_ids[] = $inserted_term['term_id'];

		return $inserted_term['term_id'];
	}

	/**
	 * Add a tax rate to the WooCommerce database.
	 *
	 * @param string $country          Tax rate country.
	 * @param string $state            Tax rate state.
	 * @param string $tax_rate         Tax rate value.
	 * @param string $is_for_shipping  Is applicable to shipping.
	 * @return void
	 */
	public static function addTaxRate( $country = '', $state = '', $tax_rate = '20.0000', $is_for_shipping = '0' ) {
		$tax_rate    = array(
			'tax_rate_country'  => $country,
			'tax_rate_state'    => $state,
			'tax_rate'          => $tax_rate,
			'tax_rate_name'     => ( (string) $tax_rate ) . 'percent',
			'tax_rate_priority' => '1',
			'tax_rate_compound' => '0',
			'tax_rate_shipping' => $is_for_shipping,
			'tax_rate_order'    => '1',
			'tax_rate_class'    => ( (string) $tax_rate ) . 'percent',
		);
		$tax_rate_id = WC_Tax::_insert_tax_rate( $tax_rate );
		return $tax_rate_id;
	}

	/**
	 * Used in the shipping tests tearDown.
	 * This basically resets the DB to the clean state and makes it ready for the next test.
	 *
	 * @return void
	 */
	public static function cleanup() {
		// Reset WooCommerce shipping data and cache.
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}woocommerce_shipping_zone_methods;" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}woocommerce_shipping_zone_locations;" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}woocommerce_shipping_zones;" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}woocommerce_tax_rates;" );
		WC_Cache_Helper::invalidate_cache_group( 'shipping_zones' );

		// Reset plugin shipping cache.
		$shipping_zones = ( new ReflectionClass( Shipping::class ) )->getProperty( 'shipping_zones' );
		$shipping_zones->setAccessible( 'true' );
		$shipping_zones->setValue( null );

		foreach ( static::$shipping_classes_ids as $id ) {
			wp_delete_term( $id, 'product_shipping_class' );
		}
		WC()->shipping()->shipping_classes = array();;
	}
}
