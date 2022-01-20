<?php

use Automattic\WooCommerce\Pinterest\Shipping;
class ShippingHelpers {

	public static $shipping_classes_ids = array();

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
	 */
	public static function addFlatRateShippingMethodToZone( $zone, $cost = 15, $no_class_cost = null, $shipping_classes_costs = array(), $tax_status = 'taxable' ) {
		$instance_id = $zone->add_shipping_method( 'flat_rate' );
		$shipping_method = WC_Shipping_Zones::get_shipping_method( $instance_id );

		$shipping_method_configuration = array(
			'woocommerce_flat_rate_title'         => 'Flat rate',
			'woocommerce_flat_rate_tax_status'    => 'taxable',
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

	public static function addNonTaxableFlatRate( $zone, $cost = 15, $no_class_cost = null, $shipping_classes_costs = array() ) {
		self::addFlatRateShippingMethodToZone( $zone, $cost, $no_class_cost, $shipping_classes_costs, 'non-taxable' );
	}

	/**
	 * Adds a predefined flat rate shipping method to zone.
	 * No additional settings.
	 */
	public static function addFreeShippingWithMinimumOrderAmount( $zone, $minimum = 10 ) {
		self::addFreeShipping( $zone, 'min_amount', $minimum );
	}

	/**
	 * Adds a predefined flat rate shipping method to zone.
	 * No additional settings.
	 */
	public static function addFreeShippingWithCouponRequirement( $zone, $minimum = 10 ) {
		self::addFreeShipping( $zone, 'either', $minimum );
	}

	/**
	 * Adds a predefined flat rate shipping method to zone.
	 * No additional settings.
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
