<?php
/**
 * Settings Page
 *
 * @package     Pinterest/Admin
 * @version     1.0.0
 */

use Automattic\WooCommerce\Admin\Features\Features;
use Automattic\WooCommerce\Admin\Features\Navigation\Menu;
use Automattic\WooCommerce\Admin\Features\Navigation\Screen;
use Automattic\WooCommerce\Admin\Features\Onboarding;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Pinterest_For_Woocommerce_Admin_Settings_Page' ) ) :

	/**
	 * Class handling the settings page and onboarding Wizard registration and rendering.
	 */
	class Pinterest_For_Woocommerce_Admin_Settings_Page {

		/**
		 * Initialize class
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'register_guide_page' ), 20 );
			add_action( 'admin_enqueue_scripts', array( $this, 'load_setup_guide_scripts' ), 20 );
			add_action( 'admin_init', array( $this, 'maybe_go_to_service_login_url' ) );
			add_filter( 'woocommerce_get_registered_extended_tasks', array( $this, 'register_task_list_item' ), 10, 1 );
			add_filter( 'woocommerce_shared_settings', array( $this, 'component_settings' ), 20 );
			add_filter( 'woocommerce_shared_settings', array( $this, 'landing_page_content' ), 20 );
		}

		/**
		 * Load scripts needed for both the Wizard and the settings view.
		 */
		public function load_common_scripts() {

			if ( $this->is_setup_guide_page() ) {
				$handle = PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE;
			} elseif (
				class_exists( 'Automattic\WooCommerce\Admin\Loader' ) &&
				\Automattic\WooCommerce\Admin\Loader::is_admin_page() &&
				Onboarding::should_show_tasks()
				) {
				$handle = PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE . '-setup-task';
			} else {
				return;
			}

			$build_path = '/assets/setup-guide';

			wp_register_style(
				$handle,
				Pinterest_For_Woocommerce()->plugin_url() . $build_path . '/style-index.css',
				array( 'wc-admin-app' ),
				PINTEREST_FOR_WOOCOMMERCE_VERSION
			);

			wp_enqueue_style( $handle );

		}


		/**
		 * Register the Onboarding Guide + Settings page
		 */
		public function register_guide_page() {

			$page_title = ( isset( $_GET['view'] ) && 'wizard' === $_GET['view'] ? __( 'Pinterest Setup Guide', 'pinterest-for-woocommerce' ) : __( 'Pinterest for WooCommerce', 'pinterest-for-woocommerce' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended --- not needed

			add_submenu_page(
				'woocommerce-marketing',
				$page_title,
				esc_html__( 'Pinterest', 'pinterest-for-woocommerce' ),
				'manage_woocommerce',
				PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE,
				array( $this, 'render_settings_page' ),
				6
			);

			if (
				! method_exists( Screen::class, 'register_post_type' ) ||
				! method_exists( Menu::class, 'add_plugin_item' ) ||
				! method_exists( Menu::class, 'add_plugin_category' ) ||
				! method_exists( Features::class, 'is_enabled' ) ||
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
					'title'      => esc_html__( 'Settings', 'pinterest-for-woocommerce' ),
					'capability' => 'manage_woocommerce',
					'url'        => PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE,
					'parent'     => 'pinterest-for-woocommerce',
				)
			);
		}


		/**
		 * Render the placeholder HTML for the React components
		 */
		public function render_settings_page() {
			echo '<div class="wrap">
				<div id="pin4wc-setup-guide"></div>
			</div>';
		}


		/**
		 * Load the scripts needed for the setup guide / settings page.
		 */
		public function load_setup_guide_scripts() {
			if ( $this->is_setup_guide_page() ) {
				$build_path = '/assets/setup-guide';
			} elseif (
				class_exists( 'Automattic\WooCommerce\Admin\Loader' ) &&
				\Automattic\WooCommerce\Admin\Loader::is_admin_page() &&
				Onboarding::should_show_tasks()
				) {
				$build_path = '/assets/setup-task';
			} else {
				return;
			}

			$handle            = PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE;
			$script_asset_path = Pinterest_For_Woocommerce()->plugin_path() . $build_path . '/index.asset.php';
			$script_info       = file_exists( $script_asset_path )
				? include $script_asset_path
				: array(
					'dependencies' => array(),
					'version'      => PINTEREST_FOR_WOOCOMMERCE_VERSION,
				);

			$script_info['dependencies'][] = 'wc-settings';

			wp_register_script(
				$handle,
				Pinterest_For_Woocommerce()->plugin_url() . $build_path . '/index.js',
				$script_info['dependencies'],
				$script_info['version'],
				true
			);

			wp_enqueue_script( $handle );

			wp_register_style(
				$handle,
				Pinterest_For_Woocommerce()->plugin_url() . $build_path . '/style-index.css',
				array( 'wc-admin-app' ),
				PINTEREST_FOR_WOOCOMMERCE_VERSION
			);

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

			return ( is_admin() && PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE === $this->get_request( 'page' ) );
		}

		/**
		 * Return Request value
		 *
		 * @since 1.0.0
		 *
		 * @param string $key the key of the request parameter we need.
		 *
		 * @return string
		 */
		protected function get_request( $key ) {

			return ! empty( $_REQUEST[ $key ] ) ? trim( sanitize_key( wp_unslash( $_REQUEST[ $key ] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		}

		/**
		 * Register the Task List item for WC-Admin.
		 *
		 * @param array $registered_tasks_list_items the list of tasks to be filtered.
		 */
		public function register_task_list_item( $registered_tasks_list_items ) {

			if (
				! class_exists( 'Automattic\WooCommerce\Admin\Loader' ) ||
				! \Automattic\WooCommerce\Admin\Loader::is_admin_page() ||
				! Onboarding::should_show_tasks()
			) {
				return;
			}

			$new_task_name = 'woocommerce_admin_add_task_pinterest_setup';

			if ( ! in_array( $new_task_name, $registered_tasks_list_items, true ) ) {
				array_push( $registered_tasks_list_items, $new_task_name );
			}

			return $registered_tasks_list_items;
		}


		/**
		 * Initialize asset data and registering it with
		 * the internal WC data registry.
		 *
		 * @param array $settings The settings array to be filtered.
		 *
		 * @return array
		 */
		public function component_settings( $settings ) {
			if ( $this->is_setup_guide_page() ) {
				$default_view = 'settings';
			} elseif (
				class_exists( 'Automattic\WooCommerce\Admin\Loader' ) &&
				\Automattic\WooCommerce\Admin\Loader::is_admin_page() &&
				Onboarding::should_show_tasks()
				) {
				$default_view = 'wizard';
			} else {
				return $settings;
			}

			$settings['pin4wc'] = array(
				'adminUrl'        => esc_url(
					add_query_arg(
						array(
							'page' => 'wc-admin',
						),
						get_admin_url( null, 'admin.php' )
					)
				),
				'serviceLoginUrl' => esc_url(
					add_query_arg(
						array(
							'page' => PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE,
							PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_go_to_service_login' => '1',
							'view' => ( isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : $default_view ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended --- not needed
						),
						get_admin_url( null, 'admin.php' )
					)
				),
				'domainToVerify'  => wp_parse_url( site_url(), PHP_URL_HOST ),
				'apiRoute'        => PINTEREST_FOR_WOOCOMMERCE_API_NAMESPACE . '/v' . PINTEREST_FOR_WOOCOMMERCE_API_VERSION,
				'pageSlug'        => PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE,
				'optionsName'     => PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME,
				'error'           => isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended --- not needed
				'pinterestLinks'  => array(
					'newAccount'       => 'https://business.pinterest.com/',
					'claimWebsite'     => 'https://help.pinterest.com/en/business/article/claim-your-website',
					'richPins'         => 'https://help.pinterest.com/en/business/article/rich-pins',
					'enhancedMatch'    => 'https://help.pinterest.com/en/business/article/enhanced-match',
					'createAdvertiser' => 'https://help.pinterest.com/en/business/article/create-an-advertiser-account',
				),
				'isSetupComplete' => Pinterest_For_Woocommerce()::get_setting( 'is_setup_complete' ),
			);

			return $settings;
		}

		public function landing_page_content( $settings ) {
			$settings['pin4wc']['landing_page'] = array(
				'welcome' => array(
					'title'     => __( 'Get your products in front of more than 475M people on Pinterest', 'pinterest-for-woocommerce' ),
					'text'      => __( 'Pinterest is a visual discovery engine people use to find inspiration for their lives and make it easier to shop for home decor, fashion and style, electronics and more. 400 million people have saved more than 300 billion Pins across a range of interests, which others with similar tastes can discover through search and recommendations.', 'pinterest-for-woocommerce' ),
					'tos_link'  => "https://business.pinterest.com/business-terms-of-service/",
					'image_url' => 'https://placehold.it/416x300/',
				),
				'features' => array(
					array(
						'title'    => __( 'Connect your account', 'pinterest-for-woocommerce' ),
						'text'     => __( 'Install the Pinterest for WooCommerce app to quickly upload your product catalog and publish Pins for items you sell. Track performance with the Pinterest Tag and keep your Pins up to date with our daily automatic updates.', 'pinterest-for-woocommerce' ),
						'image_url' => 'https://placehold.it/100x100/',
					),
					array(
						'title'    => __( 'Increase organic reach', 'pinterest-for-woocommerce' ),
						'text'     => __( 'Once you\'ve uploaded your catalog, people on Pinterest can easily discover, save and buy products from your website without any advertising spend from you.*', 'pinterest-for-woocommerce' ),
						'extra'    =>  __( '*It can take up to 5 business days for the product catalog to sync for this first time' ),
						'image_url' => 'https://placehold.it/100x100/',
					),
					array(
						'title'    => __( 'Merchant storefronts on profile', 'pinterest-for-woocommerce' ),
						'text'     => __( 'Upload your catalog via the WooCommerce for Pinterest app and transform the shop tab on your business profile into an inspiring storefront. Pinners will see featured product groups and dynamically created recommendations and can easily navigate by category. Whenever they click on your profile, they\'ll be automatically taken to your storefront.', 'pinterest-for-woocommerce' ),
						'image_url' => 'https://placehold.it/100x100/',
					),
				),
				'faq_items' => array(
					array(
						'question' => __( 'Why am I getting an “Account not connected” error message?', 'pinterest-for-woocommerce' ),
						'answer'   => __( 'Your password might have changed recently. Click Reconnect Pinterest Account and follow the instructions on screen to restore the connection.', 'pinterest-for-woocommerce' ),
					),
					array(
						'question' => __( 'I have more than one Pinterest Advertiser account. Can I connect my WooCommerce store to multiple Pinterest Advertiser accounts?', 'pinterest-for-woocommerce' ),
						'answer'   => __( 'Only one Pinterest advertiser account can be linked to each WooCommerce store. If you want to connect a different Pinterest advertiser account you will need to either: Disconnect the existing Pinterest Advertiser account from your current WooCommerce store and connect a different Pinterest Advertiser account Create another WooCommerce store and connect the additional Pinterest Advertiser account.', 'pinterest-for-woocommerce' ),
					)
				),
			);

			return $settings;
		}

		/**
		 * Handles redirection to the service login URL.
		 */
		public function maybe_go_to_service_login_url() {

			if ( ! isset( $_GET[ PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_go_to_service_login' ] ) || empty( $_REQUEST['page'] ) || PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE !== $_REQUEST['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended --- not needed
				return;
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Cheatin&#8217; huh?', 'pinterest-for-woocommerce' ) );
				return false;
			}

			$view = ! empty( $_REQUEST['view'] ) ? sanitize_key( $_REQUEST['view'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended --- not needed

			add_filter( 'allowed_redirect_hosts', array( $this, 'allow_service_login' ) );

			wp_safe_redirect( Pinterest_For_Woocommerce()::get_service_login_url( $view ) );
			exit;

		}

		/**
		 * Add the domain of API/Bridge service to the list of allowed redirect hosts.
		 *
		 * @param array $allowed_hosts the array of allowed hosts.
		 *
		 * @return array
		 */
		public function allow_service_login( $allowed_hosts ) {

			$service_domain  = Pinterest_For_Woocommerce()::get_connection_proxy_url();
			$allowed_hosts[] = wp_parse_url( $service_domain, PHP_URL_HOST );

			return $allowed_hosts;
		}

	}

endif;

return new Pinterest_For_Woocommerce_Admin_Settings_Page();
