<?php
/**
 * Ads supported countries.
 *
 * @package     Pinterest
 * @since x.x.x
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
				'AU', // Australia.
				'AT', // Austria.
				'BE', // Belgium.
				'BR', // Brazil.
				'CA', // Canada.
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
				'LU', // Luxembourg.
				'MT', // Malta.
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
	}

endif;
