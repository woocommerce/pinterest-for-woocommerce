<?php
/**
 * Pinterest for WooCommerce locale mapping class.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version x.x.x
 */

namespace Automattic\WooCommerce\Pinterest;

defined( 'ABSPATH' ) || exit;

use Exception;

/**
 * Class LocaleMapper.
 *
 * This class maps WooCommerce locale codes to Pinterest locale codes.
 * Pinterest API uses a different set of locale codes than WordPress.
 * Most of the time, the locale codes are the same, but there are some exceptions.
 * Like for example German Standard is de_DE in WordPress, but de in Pinterest.
 *
 * @since x.x.x
 */
class LocaleMapper {

	/**
	 * Pinterest locale codes.
	 * Locales have been collected on 01.02.2023 from Pinterest API response message.
	 */
	const PINTEREST_LOCALE_CODES = array(
		'it',
		'es-419',
		'ru-RU',
		'hu-HU',
		'hr-HR',
		'sv-SE',
		'de',
		'nb-NO',
		'th-TH',
		'sk-SK',
		'ro-RO',
		'es-MX',
		'uk-UA',
		'nl',
		'en-AU',
		'he-IL',
		'tr',
		'es-ES',
		'pl-PL',
		'cs-CZ',
		'en-CA',
		'fi-FI',
		'pt-PT',
		'el-GR',
		'ja',
		'ar-SA',
		'fr-CA',
		'en-GB',
		'es-AR',
		'da-DK',
		'zh-CN',
		'en-US',
		'vi-VN',
		'id-ID',
		'bg-BG',
		'zh-TW',
		'en-IN',
		'tl-PH',
		'ko-KR',
		'af-ZA',
		'ms-MY',
		'bn-IN',
		'te-IN',
		'fr',
		'hi-IN',
		'pt-BR',
	);

	/**
	 * Get Pinterest locale code for API.
	 * Pinterest API uses hyphens instead of underscores in locale codes so we need to replace them.
	 *
	 * @since x.x.x
	 * @return string
	 * @throws Exception If no matching locale code is found.
	 */
	public static function get_locale_for_api() {
		$locale = self::get_wordpress_locale();

		// If the locale is in the list of Pinterest locales, return it.
		if ( in_array( $locale, self::PINTEREST_LOCALE_CODES, true ) ) {
			return $locale;
		}

		// If the locale is not in the list of Pinterest locales, try to find a match for just the language code.
		$locale_parts = explode( '-', $locale );

		if ( in_array( $locale_parts[0], self::PINTEREST_LOCALE_CODES, true ) ) {
			return $locale_parts[0];
		}

		// If no match was found, throw an exception.
		throw new Exception( 'No matching Pinterest API locale found for ' . $locale );
	}

	/**
	 * Get WordPress locale code.
	 *
	 * @since x.x.x
	 * @return string
	 */
	private static function get_wordpress_locale() {
		$wordpress_locale = determine_locale();
		return str_replace( '_', '-', $wordpress_locale );
	}
}
