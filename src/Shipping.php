<?php
/**
 * Represents a single Pinterest shipping zone
 *
 * @since   x.x.x
 */
namespace Automattic\WooCommerce\Pinterest;

defined( 'ABSPATH' ) || exit;

use \WC_Data_Store;

/**
 * WC_Shipping_Zone class.
 */
class Shipping {

	private function map_zone( $zone ) {
		return $zone->get_locations_with_shipping();
	}

	private function get_zones() {
		$data_store = WC_Data_Store::load( 'shipping-zone' );
		$raw_zones  = $data_store->get_zones();
		$zones      = array();

		foreach ( $raw_zones as $raw_zone ) {
			$zones[] = new ShippingZone( $raw_zone );
		}
		return $zones;
	}

	public function get_shipping() {
		$zones = $this->get_zones();
		return array_map( array( $this, 'map_zone' ), $zones );
	}

}
