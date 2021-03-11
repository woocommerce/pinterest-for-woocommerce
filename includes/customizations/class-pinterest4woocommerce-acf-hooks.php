<?php
/**
 * ACF Hooks
 *
 * @author      WooCommece
 * @category    Customizations
 * @package     Pinterest4WooCommerce/Customizations
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Pinterest4WooCommerce_ACF_Hooks' ) ) :

	/**
	 * Pinterest4WooCommerce_ACF_Hooks Class.
	 */
	class Pinterest4WooCommerce_ACF_Hooks {

		/**
		 * Hook in methods.
		 */
		public function __construct() {
			// add_filter( 'acf/settings/load_json', array( $this, 'acf_json_load_point' ) );
			// add_filter( 'acf/settings/save_json', array( $this, 'acf_json_save_point' ) );
		}

		/**
		 * Set from where ACF must load JSON files
		 * @param  [array] $paths
		 * @return [array]
		 */
		public function acf_json_load_point( $paths ) {
			unset( $paths[0] );

			$paths[] = dirname( __FILE__ ) . '/acf-json';

			return $paths;
		}

		/**
		 * Set to where ACF must save JSON files
		 * @param  [string] $path
		 * @return [string]
		 */
		public function acf_json_save_point( $path ) {
			$path = dirname( __FILE__ ) . '/acf-json';

			return $path;
		}
	}

endif;

return new Pinterest4WooCommerce_ACF_Hooks();
