<?php
/**
 * Handle Admin init.
 *
 * @package     Pinterest/Admin
 * @version     1.0.0
 */

use Automattic\WooCommerce\Admin\Features\Features;
use Automattic\WooCommerce\Admin\Features\Navigation\Menu;
use Automattic\WooCommerce\Admin\Features\Navigation\Screen;
use Automattic\WooCommerce\Admin\Features\Onboarding;
use Automattic\WooCommerce\Admin\Loader;
use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Pinterest_For_Woocommerce_Admin' ) ) :

	/**
	 * Class handling the settings page and onboarding Wizard registration and rendering.
	 */
	class Pinterest_For_Woocommerce_Admin {

		/**
		 * Initialize class
		 */
		public function __construct() {
			add_action( 'admin_enqueue_scripts', array( $this, 'load_setup_guide_scripts' ) );
			add_action( 'admin_init', array( $this, 'maybe_go_to_service_login_url' ) );
			add_filter( 'woocommerce_get_registered_extended_tasks', array( $this, 'register_task_list_item' ), 10, 1 );
			add_filter( 'admin_footer', array( $this, 'load_settings' ) );
			add_filter( 'woocommerce_marketing_menu_items', array( $this, 'add_menu_items' ) );
			add_action( 'admin_menu', array( $this, 'fix_menu_paths' ) );
			add_action( 'admin_menu', array( $this, 'register_wc_admin_pages' ) );
		}


		/**
		 * Handle registration of all needed pages, depending on setup_complete status,
		 * and wether we have the new WC nav enabled or not.
		 *
		 * @return void
		 */
		public function register_wc_admin_pages() {

			$new_nav        = $this->is_new_nav_enabled();
			$setup_complete = Pinterest_For_Woocommerce()::is_setup_complete();

			if ( $new_nav && $setup_complete ) {

				// If setup is complete, add the base menu item as a category, and the settings as the main item.
				// Connection & catalog are added later on, for both new and old nav.

				wc_admin_register_page(
					array(
						'id'       => 'pinterest-for-woocommerce-category',
						'title'    => esc_html__( 'Pinterest', 'pinterest-for-woocommerce' ),
						'parent'   => 'woocommerce',
						'path'     => '/pinterest/settings',
						'nav_args' => array(
							'title'        => esc_html__( 'Pinterest', 'pinterest-for-woocommerce' ),
							'is_category'  => true,
							'menuId'       => 'plugins',
							'is_top_level' => true,
						),
					)
				);

				wc_admin_register_page(
					array(
						'id'       => 'pinterest-for-woocommerce-catalog',
						'title'    => esc_html__( 'Catalog', 'pinterest-for-woocommerce' ),
						'parent'   => 'pinterest-for-woocommerce-category',
						'path'     => '/pinterest/catalog',
						'nav_args' => array(
							'order'  => 10,
							'parent' => 'pinterest-for-woocommerce-category',
						),
					)
				);

			} elseif ( $new_nav ) {

				// Setup not complete. Add the Landing page as the main menu item.
				wc_admin_register_page(
					array(
						'id'       => 'pinterest-for-woocommerce-landing-page',
						'title'    => esc_html__( 'Pinterest', 'pinterest-for-woocommerce' ),
						'parent'   => 'woocommerce',
						'path'     => '/pinterest/landing',
						'nav_args' => array(
							'title'        => esc_html__( 'Pinterest', 'pinterest-for-woocommerce' ),
							'menuId'       => 'plugins',
							'is_top_level' => true,
						),
					)
				);

				// Allow rendering of the onboarding guide on a page refresh.
				wc_admin_register_page(
					array(
						'id'     => 'pinterest-for-woocommerce-setup-guide',
						'title'  => esc_html__( 'Setup Pinterest', 'pinterest-for-woocommerce' ),
						'parent' => '',
						'path'   => '/pinterest/onboarding',
					)
				);

			}

			$menu_items_parent = $new_nav ? 'pinterest-for-woocommerce-category' : 'toplevel_page_woocommerce-marketing';

			if ( $setup_complete ) {

				// The connection & settings pages are registered for both old & new nav, if setup is complete.

				wc_admin_register_page(
					array(
						'id'       => 'pinterest-for-woocommerce-connection',
						'title'    => esc_html__( 'Connection', 'pinterest-for-woocommerce' ),
						'parent'   => $menu_items_parent,
						'path'     => '/pinterest/connection',
						'nav_args' => array(
							'order'  => 50,
							'parent' => $menu_items_parent,
						),
					)
				);

				wc_admin_register_page(
					array(
						'id'       => 'pinterest-for-woocommerce-settings',
						'title'    => esc_html__( 'Settings', 'pinterest-for-woocommerce' ),
						'parent'   => $menu_items_parent,
						'path'     => '/pinterest/settings',
						'nav_args' => array(
							'order'  => 40,
							'parent' => $menu_items_parent,
						),
					)
				);

			}

			if ( ! $new_nav ) {

				// Allow rendering of the onboarding guide on a page refresh.
				wc_admin_register_page(
					array(
						'id'     => 'pinterest-for-woocommerce-setup-guide',
						'title'  => esc_html__( 'Setup Pinterest', 'pinterest-for-woocommerce' ),
						'parent' => 'toplevel_page_woocommerce-marketing',
						'path'   => '/pinterest/onboarding',
					)
				);
			}

			if ( $setup_complete ) {
				// Allow rendering of the landing page on a page refresh for both old & new nav, when setup is complete.
				wc_admin_register_page(
					array(
						'id'     => 'pinterest-for-woocommerce-landing-page',
						'title'  => esc_html__( 'Landing page', 'pinterest-for-woocommerce' ),
						'parent' => '',
						'path'   => '/pinterest/landing',
					)
				);
			}
		}


		/**
		 * Fix sub-menu paths. wc_admin_register_page() gets it wrong.
		 *
		 * @return void
		 */
		public function fix_menu_paths() {
			global $submenu;

			if ( ! isset( $submenu['woocommerce-marketing'] ) || $this->is_new_nav_enabled() ) {
				return;
			}

			foreach ( $submenu['woocommerce-marketing'] as &$item ) {
				// The "slug" (aka the path) is the third item in the array.
				if ( 0 === strpos( $item[2], 'wc-admin' ) ) {
					$item[2] = 'admin.php?page=' . $item[2];
				}
			}
		}


		/**
		 * Add the base menu item using the woocommerce_marketing_menu_items filter,
		 * Depending on status of setup_complete.
		 *
		 * @param array $items The array of items to be filtered.
		 *
		 * @return array
		 */
		public function add_menu_items( $items ) {

			if ( $this->is_new_nav_enabled() ) {
				return $items;
			}

			if ( Pinterest_For_Woocommerce()::is_setup_complete() ) {
				$items[] = array(
					'id'         => 'pinterest-for-woocommerce-catalog',
					'title'      => esc_html__( 'Pinterest', 'pinterest-for-woocommerce' ),
					'path'       => '/pinterest/catalog',
					'capability' => 'manage_woocommerce',
				);
			} else {
				$items[] = array(
					'id'         => 'pinterest-for-woocommerce-landing-page',
					'title'      => esc_html__( 'Pinterest', 'pinterest-for-woocommerce' ),
					'path'       => '/pinterest/landing',
					'capability' => 'manage_woocommerce',
				);
			}

			return $items;
		}


		/**
		 * Checks if the new WC navigation is enabled.
		 *
		 * @return boolean
		 */
		public function is_new_nav_enabled() {
			return method_exists( Screen::class, 'register_post_type' ) &&
				method_exists( Menu::class, 'add_plugin_item' ) &&
				method_exists( Menu::class, 'add_plugin_category' ) &&
				method_exists( Features::class, 'is_enabled' ) &&
				Features::is_enabled( 'navigation' );
		}


		/**
		 * Load the scripts needed for the setup guide / settings page.
		 */
		public function load_setup_guide_scripts() {

			if ( ! class_exists( Loader::class ) || ! Loader::is_admin_page() ) {
				return;
			}

			if ( Onboarding::should_show_tasks() ) {

				$build_path = '/assets/setup-task';

				$handle            = PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE . '-setup-task';
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

			$build_path = '/assets/setup-guide';

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

			wp_register_style(
				PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-catalog-sync',
				Pinterest_For_Woocommerce()->plugin_url() . '/assets/catalog-sync/style-index.css',
				array( 'wc-admin-app' ),
				PINTEREST_FOR_WOOCOMMERCE_VERSION
			);

			wp_enqueue_style( $handle );

		}

		/**
		 * Register the Task List item for WC-Admin.
		 *
		 * @param array $registered_tasks_list_items the list of tasks to be filtered.
		 */
		public function register_task_list_item( $registered_tasks_list_items ) {

			if (
				! class_exists( Loader::class ) ||
				! Loader::is_admin_page() ||
				! Onboarding::should_show_tasks()
			) {
				return $registered_tasks_list_items;
			}

			$new_task_name = 'woocommerce_admin_add_task_pinterest_setup';

			if ( ! in_array( $new_task_name, $registered_tasks_list_items, true ) ) {
				array_push( $registered_tasks_list_items, $new_task_name );
			}

			return $registered_tasks_list_items;
		}


		/**
		 * Load all plugin frontend data using the AssetDataRegistry class.
		 * Hooked to admin_footer as AssetDataRegistry prints at wp_print_footer_scripts.
		 *
		 * @return void
		 */
		public function load_settings() {
			if ( ! class_exists( Loader::class ) || ! class_exists( AssetDataRegistry::class ) || ! Loader::is_admin_page() ) {
				return;
			}

			Package::container()->get( AssetDataRegistry::class )->add( 'pinterest_for_woocommerce', $this->get_component_settings() );
		}


		/**
		 * Initialize asset data and registering it with
		 * the internal WC data registry.
		 *
		 * @return array
		 */
		private function get_component_settings() {

			return array(
				'pluginVersion'   => PINTEREST_FOR_WOOCOMMERCE_VERSION,
				'adminUrl'        => esc_url(
					add_query_arg(
						array(
							'page' => 'wc-admin',
						),
						admin_url( 'admin.php' )
					)
				),
				'pluginUrl'       => Pinterest_For_Woocommerce()->plugin_url(),
				'serviceLoginUrl' => esc_url(
					add_query_arg(
						array(
							'page' => 'wc-admin',
							PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_go_to_service_login' => '1',
							'view' => 'wizard',
						),
						admin_url( 'admin.php' )
					)
				),
				'domainToVerify'  => wp_parse_url( site_url(), PHP_URL_HOST ),
				'isConnected'     => ! empty( Pinterest_For_Woocommerce()::get_token()['access_token'] ),
				'apiRoute'        => PINTEREST_FOR_WOOCOMMERCE_API_NAMESPACE . '/v' . PINTEREST_FOR_WOOCOMMERCE_API_VERSION,
				'optionsName'     => PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME,
				'error'           => isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended --- not needed
				'pinterestLinks'  => array(
					'newAccount'       => 'https://business.pinterest.com/',
					'claimWebsite'     => 'https://help.pinterest.com/en/business/article/claim-your-website',
					'richPins'         => 'https://help.pinterest.com/en/business/article/rich-pins',
					'enhancedMatch'    => 'https://help.pinterest.com/en/business/article/enhanced-match',
					'createAdvertiser' => 'https://help.pinterest.com/en/business/article/create-an-advertiser-account',
					'adGuidelines'     => 'https://policy.pinterest.com/en/advertising-guidelines',
					'adDataTerms'      => 'https://policy.pinterest.com/en/ad-data-terms',
				),
				'isSetupComplete' => Pinterest_For_Woocommerce()::is_setup_complete(),
				'countryTos'      => Pinterest_For_Woocommerce()::get_applicable_tos(),
			);
		}

		/**
		 * Handles redirection to the service login URL.
		 */
		public function maybe_go_to_service_login_url() {

			if ( ! isset( $_GET[ PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_go_to_service_login' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended --- not needed
				return;
			}

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
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

return new Pinterest_For_Woocommerce_Admin();
