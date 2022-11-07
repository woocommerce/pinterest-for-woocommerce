<?php
/**
 * Ads supported countries.
 *
 * @package     Pinterest
 * @since 1.0.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Pinterest_For_Woocommerce_Ads_Supported_Countries' ) ) :

	/**
	 * Class handling the settings page and onboarding Wizard registration and rendering.
	 */
	class Pinterest_For_Woocommerce_Ads_Supported_Countries {

		/**
		 * Get the alpha-2 country codes where Pinterest advertises.
		 *
		 * @see https://help.pinterest.com/en/business/availability/ads-availability
		 *
		 * @return string[]
		 */
		public static function get_countries() {
			return array(
				'AR', // Argentina.
				'AU', // Australia.
				'AT', // Austria.
				'BE', // Belgium.
				'BR', // Brazil.
				'CA', // Canada.
				'CL', // Chile.
				'CO', // Colombia.
				'CY', // Cyprus.
				'CZ', // Czech Republic.
				'DK', // Denmark.
				'FI', // Finland.
				'FR', // France.
				'DE', // Germany.
				'GR', // Greece.
				'HU', // Hungary.
				'IE', // Ireland.
				'IT', // Italy.
				'JP', // Japan.
				'LU', // Luxembourg.
				'MT', // Malta.
				'MX', // Mexico.
				'NL', // Netherlands.
				'NZ', // New Zealand.
				'NO', // Norway.
				'PL', // Poland.
				'PT', // Portugal.
				'RO', // Romania.
				'SK', // Slovakia.
				'ES', // Spain.
				'SE', // Sweden.
				'CH', // Switzerland.
				'GB', // United Kingdom (UK).
				'US', // United States (US).
			);
		}

		/**
		 * Check if user selected location is in the list of ads supported countries.
		 *
		 * @since x.x.x
		 *
		 * @return bool Wether this is ads supported location.
		 */
		public static function is_ads_supported_country() {
			$store_country = Pinterest_For_Woocommerce()::get_base_country() ?? 'US';
			return in_array( $store_country, self::get_countries(), true );
		}
	}

endif;
