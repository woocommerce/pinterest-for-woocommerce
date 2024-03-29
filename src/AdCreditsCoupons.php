<?php
/**
 * Pinterest for WooCommerce Ads Credits Coupons
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.2.5
 */

namespace Automattic\WooCommerce\Pinterest;

use DateTime;
use DateTimeZone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Handling ad credits.
 */
class AdCreditsCoupons {

	/**
	 * List of Ads Credits allowed currencies.
	 *
	 * @since 1.3.17
	 *
	 * @var array
	 */
	public static $allowed_currencies = array(
		'USD',
		'GBP',
		'EUR',
		'BRL',
		'AUD',
		'CAD',
		'MXN',
		'PLN',
		'CHF',
		'DKK',
		'RON',
		'SEK',
		'NZD',
		'HUF',
		'NOK',
		'JPY',
		'CZK',
		'ARS',
	);

	/**
	 * 2024 copon code.
	 *
	 * @var string
	 */
	public static $coupon_for_2024 = '1b2c680bdf2b89eecb3384b10db2ca6a0b3824d82bbe63939b35e5720604cde4';

	/**
	 * Get a valid coupon for the merchant.
	 *
	 * @since 1.2.5
	 * @since 1.3.17 update logic for new data format.
	 *
	 * @return string|false Coupon string or false if no coupon was found.
	 */
	public static function get_coupon_for_merchant() {
		$currency = get_woocommerce_currency();
		if ( ! in_array( $currency, self::$allowed_currencies, true ) ) {
			return false;
		}

		return self::$coupon_for_2024;
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
