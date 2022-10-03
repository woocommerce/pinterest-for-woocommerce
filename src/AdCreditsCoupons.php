<?php
/**
 * Pinterest for WooCommerce Ads Credits Coupons
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     x.x.x
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Handling ad credits.
 */
class AdCreditsCoupons {

	/**
	 * @var array $currency_coupons_map Mapping of coupons to currency available for that currency.
	 */
	public static $currency_coupons_map = array(
		'USD' =>
			array(
				'TESTING_WOO_FUTURE_01',
			),
		'GBP' =>
			array(
				'TESTING_WOO_FUTURE_01',
			),
		'CAD' =>
			array(
				'TESTING_WOO_FUTURE_01',
			),
		'EUR' =>
			array(
				'TESTING_WOO_FUTURE_01',
			),
		'AUD' =>
			array(
				'TESTING_WOO_FUTURE_01',
			),
	);

	/**
	 * Get a valid coupon for merchant.
	 *
	 * @since x.x.x
	 *
	 * @return string|false Coupon string of false if no coupon was found.
	 */
	public static function get_coupon_for_merchant() {
		$currency = get_woocommerce_currency();
		$coupons  = self::$currency_coupons_map[ $currency ] ?? array();
		if ( empty( $coupons ) ) {
			return false;
		}

		return reset( $coupons );
	}

	/**
	 * Check if there is a valid coupon for the user currency.
	 *
	 * @return bool Wether there is a valid coupon for the merchant.
	 */
	public static function has_valid_coupon_for_merchant() {
		return self::get_coupon_for_merchant() !== false;
	}

}
