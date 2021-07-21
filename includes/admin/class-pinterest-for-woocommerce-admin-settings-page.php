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
			add_action( 'admin_enqueue_scripts', array( $this, 'load_setup_guide_scripts' ), 1 ); // TODO: prio 20 breaks woocommerce_admin_pages_list filter.
			add_action( 'admin_init', array( $this, 'maybe_go_to_service_login_url' ) );
			add_filter( 'woocommerce_get_registered_extended_tasks', array( $this, 'register_task_list_item' ), 10, 1 );
			add_filter( 'woocommerce_shared_settings', array( $this, 'component_settings' ), 20 );
			add_filter( 'woocommerce_shared_settings', array( $this, 'landing_page_content' ), 20 );
			add_filter( 'woocommerce_marketing_menu_items', array( $this, 'add_menu_items' ) );
			add_action( 'admin_menu', array( $this, 'fix_menu_paths' ) );

			add_action(
				'admin_menu',
				function() {

					wc_admin_register_page(
						array(
							'title'    => __( 'connection', 'pinterest-for-woocommerce' ), // TODO: title
							'parent'   => 'toplevel_page_woocommerce-marketing', // TODO:
							'path'     => '/pinterest/connection',
							'id'       => 'pinterest-for-woocommerce-connection',
							'nav_args' => array(
								'order'  => 40,
								'parent' => 'toplevel_page_woocommerce-marketing', // TODO:
							),
						)
					);

					wc_admin_register_page(
						array(
							'title'    => __( 'catalog', 'pinterest-for-woocommerce' ), // TODO: title
							'parent'   => 'toplevel_page_woocommerce-marketing', // TODO:
							'path'     => '/pinterest/catalog',
							'id'       => 'pinterest-for-woocommerce-catalog',
							'nav_args' => array(
								'order'  => 40,
								'parent' => 'toplevel_page_woocommerce-marketing', // TODO:
							),
						)
					);

					wc_admin_register_page(
						array(
							'title'    => __( 'landing', 'pinterest-for-woocommerce' ), // TODO: title
							'parent'   => 'toplevel_page_woocommerce-marketing', // TODO:
							'path'     => '/pinterest/landing',
							'id'       => 'pinterest-for-woocommerce-landing-page',
							'nav_args' => array(
								'order'  => 40,
								'parent' => 'toplevel_page_woocommerce-marketing', // TODO:
							),
						)
					);
				}
			);

		}

		public function fix_menu_paths() {
			global $submenu;

			if ( ! isset( $submenu['woocommerce-marketing'] ) ) {
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
		 *
		 * @param array $items
		 *
		 * @return array
		 */
		public function add_menu_items( $items ) {

			if ( $this->is_new_nav_enabled() ) {
				return $items;
			}

			if ( Pinterest_For_Woocommerce()::is_setup_complete() ) {
				$items[] = array(
					'id'         => 'pinterest-for-woocommerce-settings',
					'title'      => __( 'Pinterest', 'pinterest-for-woocommerce' ),
					'path'       => '/pinterest/settings',
					'capability' => 'manage_woocommerce',
				);
			} else {
				$items[] = array(
					'id'         => 'pinterest-for-woocommerce-setup-guide',
					'title'      => __( 'Pinterest Onboarding', 'pinterest-for-woocommerce' ),
					'path'       => '/pinterest/onboarding',
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

			if ( ! class_exists( 'Automattic\WooCommerce\Admin\Loader' ) || ! \Automattic\WooCommerce\Admin\Loader::is_admin_page() ) {
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
				return $registered_tasks_list_items;
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

			if ( ! class_exists( 'Automattic\WooCommerce\Admin\Loader' ) || ! \Automattic\WooCommerce\Admin\Loader::is_admin_page() ) {
				return;
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
							'view' => 'wizard',
						),
						get_admin_url( null, 'admin.php' )
					)
				),
				'domainToVerify'  => wp_parse_url( site_url(), PHP_URL_HOST ),
				'isConnected'     => ! empty( Pinterest_For_Woocommerce()::get_token()['access_token'] ),
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
					'adGuidelines'     => 'https://policy.pinterest.com/en/advertising-guidelines',
					'adDataTerms'      => 'https://policy.pinterest.com/en/ad-data-terms',
				),
				'isSetupComplete' => Pinterest_For_Woocommerce()::is_setup_complete(),
			);

			return $settings;
		}


		/**
		 * Adds the content of the landing page.
		 *
		 * @param array $settings The settings array to be filtered.
		 *
		 * @return array
		 */
		public function landing_page_content( $settings ) {

			if ( ! class_exists( 'Automattic\WooCommerce\Admin\Loader' ) || ! \Automattic\WooCommerce\Admin\Loader::is_admin_page() ) {
				return;
			}

			$settings['pin4wc']['landing_page'] = array(
				'welcome'   => array(
					'title'     => esc_html__( 'Get your products in front of more than 475M people on Pinterest', 'pinterest-for-woocommerce' ),
					'text'      => esc_html__( 'Pinterest is a visual discovery engine people use to find inspiration for their lives! More than 475 million people have saved more than 300 billion Pins. Making it easier to turn inspiration into their next purchase.', 'pinterest-for-woocommerce' ),
					'tos_link'  => 'https://business.pinterest.com/business-terms-of-service/',
					'image_url' => 'http://placehold.it/416x300/',
				),
				'features'  => array(
					array(
						'title'     => esc_html__( 'Connect your account', 'pinterest-for-woocommerce' ),
						'text'      => esc_html__( 'Install the Pinterest for WooCommerce app to quickly upload your product catalog and publish Pins for items you sell. Track performance with the Pinterest Tag and keep your Pins up to date with our daily automatic updates.', 'pinterest-for-woocommerce' ),
						'image_url' => 'http://placehold.it/100x100/',
					),
					array(
						'title'     => esc_html__( 'Increase organic reach', 'pinterest-for-woocommerce' ),
						'text'      => esc_html__( 'Once you\'ve uploaded your catalog, people on Pinterest can easily discover, save and buy products from your website without any advertising spend from you.*', 'pinterest-for-woocommerce' ),
						'extra'     => esc_html__( '*It can take up to 5 business days for the product catalog to sync for this first time.', 'pinterest-for-woocommerce' ),
						'image_url' => 'http://placehold.it/100x100/',
					),
					array(
						'title'     => esc_html__( 'Merchant storefronts on profile', 'pinterest-for-woocommerce' ),
						'text'      => esc_html__( 'Upload your catalog via the WooCommerce for Pinterest app and on the Shop tab By syncing your catalog to Pinterest, Pinners will be able to view your products as Pins on your Shop tab—your unique storefront on Pinterest.', 'pinterest-for-woocommerce' ),
						'image_url' => 'http://placehold.it/100x100/',
					),
				),
				'faq_items' => array(
					array(
						'question' => esc_html__( 'Why am I getting an “Account not connected” error message?', 'pinterest-for-woocommerce' ),
						'answer'   => esc_html__( 'Your password might have changed recently. Click Reconnect Pinterest Account and follow the instructions on screen to restore the connection.', 'pinterest-for-woocommerce' ),
					),
					array(
						'question' => esc_html__( 'I have more than one Pinterest Advertiser account. Can I connect my WooCommerce store to multiple Pinterest Advertiser accounts?', 'pinterest-for-woocommerce' ),
						'answer'   => esc_html__( 'Only one Pinterest advertiser account can be linked to each WooCommerce store. If you want to connect a different Pinterest advertiser account you will need to either Disconnect the existing Pinterest Advertiser account from your current WooCommerce store and connect a different Pinterest Advertiser account, or Create another WooCommerce store and connect the additional Pinterest Advertiser account.', 'pinterest-for-woocommerce' ),
					),
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

return new Pinterest_For_Woocommerce_Admin_Settings_Page();
