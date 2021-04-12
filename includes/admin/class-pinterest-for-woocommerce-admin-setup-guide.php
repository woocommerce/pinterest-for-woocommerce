<?php
/**
 * Setup Guide
 *
 * @author      WooCommerce
 * @category    Admin
 * @package     Pinterest_For_Woocommerce/Admin
 * @version     1.0.0
 */

use Automattic\WooCommerce\Admin\Features\Features;
use Automattic\WooCommerce\Admin\Features\Navigation\Menu;
use Automattic\WooCommerce\Admin\Features\Navigation\Screen;
use Automattic\WooCommerce\Admin\Features\Onboarding;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Pinterest_For_Woocommerce_Admin_Setup_Guide' ) ) :

	class Pinterest_For_Woocommerce_Admin_Setup_Guide {

		public function __construct() {
			add_action( 'admin_menu', array( $this, 'register_guide_page' ), 20 );
			add_action( 'admin_enqueue_scripts', array( $this, 'load_setup_guide_scripts' ), 20 );
			//add_action( 'admin_enqueue_scripts', array( $this, 'add_task_register_script' ) );
		}

		public function register_guide_page() {
			function pin4wc_setup_guide_page(){
				echo '<div class="wrap">
					<div id="pin4wc-setup-guide-app"></div>
				</div>';
			}

			add_submenu_page(
				null,
				__( 'Pinterest Setup Guide', 'pinterest-for-woocommerce' ),
				__( 'Pinterest Setup Guide', 'pinterest-for-woocommerce' ),
				'manage_options',
				PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE,
				'pin4wc_setup_guide_page'
			);

			add_menu_page(
				__( 'Pinterest Settings', 'pinterest-for-woocommerce' ),
				__( 'Pinterest Settings', 'pinterest-for-woocommerce' ),
				'manage_woocommerce',
				PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE,
				'pin4wc_setup_guide_page'
			);

			if (
				! method_exists( Screen::class, 'register_post_type' ) ||
				! method_exists( Menu::class, 'add_plugin_item' ) ||
				! method_exists( Menu::class, 'add_plugin_category' ) ||
				//! class_exists( 'Features' ) ||
				! Features::is_enabled( 'navigation' )
			) {
				return;
			}

			Menu::add_plugin_category(
				array(
					'id'     => 'pinterest-for-woocommerce',
					'title'  => __( 'Pinterest', 'pinterest-for-woocommerce' ),
					'parent' => 'woocommerce',
				)
			);

			Menu::add_plugin_item(
				array(
					'id'         => 'pin4wcSettings',
					'title'      => __( 'Settings', 'pin4wc' ),
					'capability' => 'manage_woocommerce',
					'url'        => PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE,
					'parent'     => 'pinterest-for-woocommerce',
				)
			);
		}

		public function load_setup_guide_scripts() {
			if ( ! $this->is_setup_guide_page() ) {
				return;
			}

			$handle            = PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE;
			$build_path        = '/assets/setup-guide';
			$script_asset_path = Pinterest_For_Woocommerce()->plugin_path() . $build_path . '/index.asset.php';
			$script_info       = file_exists( $script_asset_path )
				? include $script_asset_path
				: array(
					'dependencies' => array(),
					'version'      => PINTEREST_FOR_WOOCOMMERCE_VERSION,
				);

			wp_register_script(
				$handle,
				Pinterest_For_Woocommerce()->plugin_url() . $build_path . '/index.js',
				$script_info['dependencies'],
				$script_info['version'],
				true
			);

			wp_localize_script(
				$handle,
				'pin4wcSetupGuide',
				array(
					'adminUrl'        => esc_url( admin_url() ),
					'serviceLoginUrl' => esc_url( add_query_arg(
						array(
							'page' => PINTEREST_FOR_WOOCOMMERCE_PREFIX,
							PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_go_to_service_login' => '1',
						),
						admin_url( 'admin.php' )
					) ),
					'domainToVerify'  => wp_parse_url( site_url(), PHP_URL_HOST ),
					'apiRoute'        => PINTEREST_FOR_WOOCOMMERCE_API_NAMESPACE . '/v' . PINTEREST_FOR_WOOCOMMERCE_API_VERSION,
					'pageSlug'        => PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE,
					'optionsName'     => PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME,
					'error'           => isset( $_GET['error'] ) ? esc_html( $_GET['error'] ) : '', //phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification,
					'pinterestLinks'  => array(
						'newAccount'    => 'https://business.pinterest.com/',
						'verifyDomain'  => 'https://help.pinterest.com/en/business/article/claim-your-website',
						'richPins'      => 'https://help.pinterest.com/en/business/article/rich-pins',
						'enhancedMatch' => 'https://help.pinterest.com/en/business/article/enhanced-match',
					)
				)
			);

			wp_register_style(
				$handle,
				Pinterest_For_Woocommerce()->plugin_url() . $build_path . '/style-index.css',
				array( 'wc-admin-app' ),
				PINTEREST_FOR_WOOCOMMERCE_VERSION
			);

			wp_enqueue_script( $handle );
			wp_enqueue_style( $handle );
		}

		/**
		 * Return if it's the Setup Guide page
		 *
		 * @since 1.0.0
		 *
		 * @return boolean
		 */
		protected function is_setup_guide_page() {

			return ( is_admin() && PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE === $this->get_request( 'page', true ) );
		}

		/**
		 * Return Request value
		 *
		 * @since 1.0.0
		 *
		 * @param string $key
		 * @param bollean $sanitize If must sanitize the value as a key
		 *
		 * @return string
		 */
		protected function get_request( $key, $sanitize = false ) {

			$request = ! empty( $_REQUEST[ $key ] ) ? trim( $_REQUEST[ $key ] ) : ''; // phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification

			return $sanitize ? sanitize_key( $request ) : $request;
		}
	}

endif;

return new Pinterest_For_Woocommerce_Admin_Setup_Guide();
