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

	/**
	 * Shipping supports:
	 * - free shipping without additional settings.
	 * - free shipping with minimum order value. Minimum is tested over single item product. ( still, better than nothing )
	 */

	public function get_zones() {
		$data_store = WC_Data_Store::load( 'shipping-zone' );
		$raw_zones  = $data_store->get_zones();
		$zones      = array();

		foreach ( $raw_zones as $raw_zone ) {
			$zones[] = new ShippingZone( $raw_zone );
		}
		return $zones;
	}
}
