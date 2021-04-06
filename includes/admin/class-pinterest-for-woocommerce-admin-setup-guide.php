<?php
/**
 * Setup Guide
 *
 * @author      WooCommerce
 * @category    Admin
 * @package     Pinterest_For_Woocommerce/Admin
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Pinterest_For_Woocommerce_Admin_Setup_Guide' ) ) :

	class Pinterest_For_Woocommerce_Admin_Setup_Guide {

		public $page_slug = PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE;

		public function __construct() {
			add_action( 'admin_menu', array( $this, 'register_guide_page' ), 20 );
			add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ), 20 );
		}


		public function register_guide_page() {
			add_submenu_page(
				null,
				__( 'Pinterest Setup Guide', 'pinterest-for-woocommerce' ),
				__( 'Pinterest Setup Guide', 'pinterest-for-woocommerce' ),
				'manage_options',
				$this->page_slug,
				array( $this, 'page_wrapper' )
			);
		}

		public function page_wrapper() {
			?>
			<div class="wrap">
				<div id="pin4wc-setup-guide-app"></div>
			</div>
			<?php
		}

		public function load_scripts() {
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
					'serviceLoginUrl' => esc_url( Pinterest_For_Woocommerce()::get_service_login_url() ), // TODO:
					'reviewFieldsUrl' => esc_url( admin_url( 'options-general.php?page=' . PINTEREST_FOR_WOOCOMMERCE_PREFIX . '&tab=relationships' ) ),
					'settingsUrl'     => esc_url( admin_url( 'options-general.php?page=' . PINTEREST_FOR_WOOCOMMERCE_PREFIX . '&tab=general' ) ),
					'apiRoute'        => PINTEREST_FOR_WOOCOMMERCE_API_NAMESPACE . '/v' . PINTEREST_FOR_WOOCOMMERCE_API_VERSION,
					'pageSlug'        => PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE,
					'optionsName'     => PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME,
					'error'           => isset( $_GET['error'] ) ? esc_html( $_GET['error'] ) : '', //phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification,
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

			return ( is_admin() && $this->page_slug === $this->get_request( 'page', true ) );
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
