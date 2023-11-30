<?php
/**
 * Pinterest for WooCommerce Ads Credits Coupons
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.2.5
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
		'USD' => 'V09PQ09NTUVSQ0VfMTQ2ODQxNF9DUkVESVRfMjAyNA==',
		'GBP' => 'V09PQ09NTUVSQ0VfMTQ2ODQxNF9DUkVESVRfMjAyNA==',
		'EUR' => 'V09PQ09NTUVSQ0VfMTQ2ODQxNF9DUkVESVRfMjAyNA==',
		'BRL' => 'V09PQ09NTUVSQ0VfMTQ2ODQxNF9DUkVESVRfMjAyNA==',
		'AUD' => 'V09PQ09NTUVSQ0VfMTQ2ODQxNF9DUkVESVRfMjAyNA==',
		'CAD' => 'V09PQ09NTUVSQ0VfMTQ2ODQxNF9DUkVESVRfMjAyNA==',
		'MXN' => 'V09PQ09NTUVSQ0VfMTQ2ODQxNF9DUkVESVRfMjAyNA==',
		'PLN' => 'V09PQ09NTUVSQ0VfMTQ2ODQxNF9DUkVESVRfMjAyNA==',
		'CHF' => 'V09PQ09NTUVSQ0VfMTQ2ODQxNF9DUkVESVRfMjAyNA==',
		'DKK' => 'V09PQ09NTUVSQ0VfMTQ2ODQxNF9DUkVESVRfMjAyNA==',
		'RON' => 'V09PQ09NTUVSQ0VfMTQ2ODQxNF9DUkVESVRfMjAyNA==',
		'SEK' => 'V09PQ09NTUVSQ0VfMTQ2ODQxNF9DUkVESVRfMjAyNA==',
		'NZD' => 'V09PQ09NTUVSQ0VfMTQ2ODQxNF9DUkVESVRfMjAyNA==',
		'HUF' => 'V09PQ09NTUVSQ0VfMTQ2ODQxNF9DUkVESVRfMjAyNA==',
		'NOK' => 'V09PQ09NTUVSQ0VfMTQ2ODQxNF9DUkVESVRfMjAyNA==',
		'JPY' => 'V09PQ09NTUVSQ0VfMTQ2ODQxNF9DUkVESVRfMjAyNA==',
		'CZK' => 'V09PQ09NTUVSQ0VfMTQ2ODQxNF9DUkVESVRfMjAyNA==',
		'ARS' => 'V09PQ09NTUVSQ0VfMTQ2ODQxNF9DUkVESVRfMjAyNA==',
	);

	/**
	 * Get a valid coupon for merchant.
	 *
	 * @since 1.2.5
	 *
	 * @return string|false Coupon string of false if no coupon was found.
	 */
	public static function get_coupon_for_merchant() {
		$currency = get_woocommerce_currency();
		return self::$currency_coupons_map[ $currency ] ?? false;
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
