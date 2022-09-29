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
				'TESTING_WOO_FUTURE_02',
				'TESTING_WOO_FUTURE_03',
				'TESTING_WOO_FUTURE_04',
				'TESTING_WOO_FUTURE_05',
			),
		'GBP' =>
			array(
				'TESTING_WOO_FUTURE_01',
				'TESTING_WOO_FUTURE_02',
				'TESTING_WOO_FUTURE_03',
				'TESTING_WOO_FUTURE_04',
				'TESTING_WOO_FUTURE_05',
			),
		'CAD' =>
			array(
				'TESTING_WOO_FUTURE_01',
				'TESTING_WOO_FUTURE_02',
				'TESTING_WOO_FUTURE_03',
				'TESTING_WOO_FUTURE_04',
				'TESTING_WOO_FUTURE_05',
			),
		'EUR' =>
			array(
				'TESTING_WOO_FUTURE_01',
				'TESTING_WOO_FUTURE_02',
				'TESTING_WOO_FUTURE_03',
				'TESTING_WOO_FUTURE_04',
				'TESTING_WOO_FUTURE_05',
			),
		'AUD' =>
			array(
				'TESTING_WOO_FUTURE_01',
				'TESTING_WOO_FUTURE_02',
				'TESTING_WOO_FUTURE_03',
				'TESTING_WOO_FUTURE_04',
				'TESTING_WOO_FUTURE_05',
			),
	);

	/**
	 * Check if there is a valid coupon for the user currency.
	 *
	 * @return string|false Coupon string or false if no coupon was found.
	 */
	public static function has_valid_coupon_for_merchant() {
		$currency = get_woocommerce_currency();
		$coupons  = self::$currency_coupons_map[ $currency ] ?? array();
		if ( empty( $coupons ) ) {
			return false;
		}

		return reset( $coupons );
	}
}
