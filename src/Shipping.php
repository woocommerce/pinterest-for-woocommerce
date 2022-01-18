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

	static $shipping_zones = null;

	/**
	 * Shipping supports:
	 * - free shipping without additional settings.
	 * - free shipping with minimum order value. Minimum is tested over single item product. ( still, better than nothing )
	 * - variable product
	 * - locations mixing: continent + country + state
	 */
	public static function get_zones() {
		$data_store = WC_Data_Store::load( 'shipping-zone' );
		$raw_zones  = $data_store->get_zones();
		$zones      = array();

		foreach ( $raw_zones as $raw_zone ) {
			$zones[] = new ShippingZone( $raw_zone );
		}
		return $zones;
	}

	public function prepare_shipping_column( $product ) {
		$shipping_zones = self::get_shipping_zones();
		$lines          = array();
		foreach ( $shipping_zones as $zone ) {
			$shipping_info = $zone->get_locations_with_shipping();
			if ( is_null( $shipping_info ) ) {
				continue;
			}
			$best_shipping = self::get_best_shipping_with_cost( $shipping_info, $product );
			if ( null === $best_shipping ) {
				continue;
			}
			foreach ( $shipping_info['locations'] as $location ) {
				$currency = get_woocommerce_currency();
				$lines[]  = "$location->country:$location->state:$best_shipping->name:$best_shipping->cost $currency";
			}
		}
		return implode( ",", $lines );
	}

	private static function get_shipping_zones() {
		if ( null !== self::$shipping_zones ) {
			return self::$shipping_zones;
		}
		self::$shipping_zones = self::get_zones();
		return self::$shipping_zones;
	}

	public static function is_free_shipping_available( $is_available, $package, $shipping_method ) {
		if ( $is_available ) {
			return true;
		}

		if ( ! ( $shipping_method instanceof \WC_Shipping_Free_Shipping ) ) {
			return $is_available;
		}

		if ( ! in_array( $shipping_method->requires, array( 'min_amount' ), true ) ) {
			return $is_available;
		}

		$has_met_min_amount = $package['cart_subtotal'] >= $shipping_method->min_amount;

		return $has_met_min_amount;
	}

	private static function get_best_shipping_with_cost( $zone, $product ) {
		$package = self::put_product_into_a_shipping_package( $product, reset( $zone['locations'] ) );
		$rates   = array();

		add_filter( 'woocommerce_shipping_free_shipping_is_available', array( static::class, 'is_free_shipping_available' ), 10, 3 );
		foreach ( $zone['shipping_methods'] as $shipping_method ) {
				// Use + instead of array_merge to maintain numeric keys.
				$rates += $shipping_method->get_rates_for_package( $package );
		}
		remove_filter( 'woocommerce_shipping_free_shipping_is_available', array( static::class, 'is_free_shipping_available' ), 10 );

		// Check if shipping methods have returned any valid rates.
		if ( empty( $rates ) ) {
			return null;
		}

		$best_cost = INF;
		$best_name = '';
		foreach ( $rates as $rate ) {
			$cost = $rate->get_cost();
			if ( $cost < $best_cost ) {
				$best_cost = $cost;
				$best_name = $rate->get_label();
			}
			$best_cost = $cost < $best_cost ? $cost : $best_cost;

		}

		return (object) array(
			'cost' => $best_cost,
			'name' => $best_name,
		);
	}

	public static function put_product_into_a_shipping_package( $product, $location ) {
		include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
		$cart_item = array(
			'key'          => 0,
			'product_id'   => $product->get_id(),
			'variation_id' => null,
			'variation'    => null,
			'quantity'     => 1,
			'data'         => $product,
			'data_hash'    => wc_get_cart_item_data_hash( $product ),
			'line_total'   => wc_remove_number_precision( (float) $product->get_price() ),
		);

		return array(
			'contents'        => array( $cart_item ),
			'contents_cost'   => (float) $product->get_price(),
			'applied_coupons' => array(),
			'user'            => array(
				'ID' => get_current_user_id(),
			),
			'destination'     => array(
				'country'   => $location->country,
				'state'     => $location->state,
				'postcode'  => '',
				'city'      => '',
				'address'   => '',
				'address_1' => '',
				'address_2' => '',
			),
			'cart_subtotal'   => (float) $product->get_price(),
		);
	}

}
