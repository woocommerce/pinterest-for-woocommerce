<?php
/**
 * Settings Page
 *
 * @author      WooCommerce
 * @category    Admin
 * @package     Pinterest/Admin
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Pinterest_For_Woocommerce_Admin_Settings_Page' ) ) :

	class Pinterest_For_Woocommerce_Admin_Settings_Page {

		public static $errors   = array();
		public static $messages = array();
		public $nonce_save_key  = PINTEREST4WOOCOMMERCE_PREFIX . '-save-settings';
		public $label           = 'Pinterest4wc';

		public function __construct() {
			add_action( 'admin_menu', array( $this, 'register_menu_item' ) );
			add_action( 'admin_init', array( $this, 'maybe_go_to_service_login_url' ) );
		}


		public function register_menu_item() {

			$hook = add_submenu_page(
				'woocommerce-marketing',
				esc_html__( 'Pinterest for WooCommerce', 'pinterest-for-wordpress' ),
				esc_html__( 'Pinterest', 'pinterest-for-wordpress' ),
				'manage_woocommerce',
				PINTEREST4WOOCOMMERCE_PREFIX,
				array( $this, 'submenu_output' ),
				6
			);

			add_action( 'load-' . $hook, array( $this, 'save' ) );
		}

		public function maybe_go_to_service_login_url() {

			if ( ! isset( $_GET[ PINTEREST4WOOCOMMERCE_PREFIX . '_go_to_service_login' ] ) || empty( $_REQUEST['page'] ) || PINTEREST4WOOCOMMERCE_PREFIX !== $_REQUEST['page'] ) {
				return;
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				self::add_error( esc_html__( 'Cheatin&#8217; huh?', 'pinterest-for-woocommerce' ) );
				return false;
			}

			wp_redirect( Pinterest_For_Woocommerce()::get_service_login_url() );
			exit;

		}


		/**
		 * Settings HTML output
		 *
		 * @since 1.0.0
		 */
		public function submenu_output() {

			pinterest_for_woocommerce_get_template_part( 'admin/settings' );
			wp_nonce_field( $this->nonce_save_key, $this->nonce_save_key );

		}



		/** Save Process Methods ******************************************** */


		/**
		 * Save settings process
		 *
		 * @since 1.0.0
		 */
		public function save() {

			// Is not saving
			if ( ! isset( $_REQUEST[ $this->nonce_save_key ] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}

			if ( empty( $_REQUEST['page'] ) || PINTEREST4WOOCOMMERCE_PREFIX !== $_REQUEST['page'] ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}

			// // Is saving and doesn't have permissions or nonce doesn't matches
			if ( ! current_user_can( 'manage_options' ) || empty( $_REQUEST[ $this->nonce_save_key ] ) || ! wp_verify_nonce( $_REQUEST[ $this->nonce_save_key ], $this->nonce_save_key ) ) {
				self::add_error( esc_html__( 'Cheatin&#8217; huh?', 'pinterest-for-woocommerce' ) );
				return false;
			}

			// Save settings

			// ...
		}


		/**
		* Add an error.
		*
		* @since 1.0.0
		*
		* @param string $text Message.
		*/
		public static function add_error( $text ) {
			self::$errors[] = $text;
		}


		/**
		* Add a message.
		*
		* @since 1.0.0
		*
		* @param string $text Message.
		*/
		public static function add_message( $text ) {
			self::$messages[] = $text;
		}


		/**
		* Output errors or messages.
		*/
		public static function show_messages() {
			if ( count( self::$errors ) > 0 ) {
				foreach ( self::$errors as $error ) {
					echo '<div id="message" class="error inline"><p><strong>' . esc_html( $error ) . '</strong></p></div>';
				}
			} elseif ( count( self::$messages ) > 0 ) {
				foreach ( self::$messages as $message ) {
					echo '<div id="message" class="updated inline"><p><strong>' . esc_html( $message ) . '</strong></p></div>';
				}
			}
		}

		/** AJAX Handlers *************************************************** */

	}

endif;

return new Pinterest_For_Woocommerce_Admin_Settings_Page();
