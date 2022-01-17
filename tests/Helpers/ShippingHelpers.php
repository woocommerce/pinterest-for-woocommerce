<?php

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
	public static function addFlatRateShippingMethodToZone( $zone, $no_class_cost = null ) {
		$instance_id = $zone->add_shipping_method( 'flat_rate' );
		$shipping_method = WC_Shipping_Zones::get_shipping_method( $instance_id );

		$shipping_method_configuration = array(
			'woocommerce_flat_rate_title'         => 'Flat rate',
			'woocommerce_flat_rate_tax_status'    => 'taxable',
			'woocommerce_flat_rate_cost'          => '15',
			'woocommerce_flat_rate_type'          => 'class',
			'instance_id'                         => $instance_id
		);

		if ( $no_class_cost ) {
			$shipping_method_configuration['woocommerce_flat_rate_no_class_cost'] = $no_class_cost;
		}

		$shipping_method->set_post_data( $shipping_method_configuration );

		// Cheat process_admin_options that this is a shipping method save request.
		$_REQUEST['instance_id'] = $instance_id;
		$shipping_method->process_admin_options();

		WC_Cache_Helper::invalidate_cache_group( 'shipping_zones' );
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
		$shipping_classes_ids[] = $inserted_term['term_id'];
	}

	public static function cleanupShippingClasses() {
		foreach ( static::$shipping_classes_ids as $id ) {
			wp_delete_term( $id, 'product_shipping_class' );
		}
	}
}
