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


		/**
		 * Settings HTML output
		 *
		 * @since 1.0.0
		 */
		public function submenu_output() {

			?>
			<h2><?php echo esc_html__( 'Connection', 'pinterest-for-woocommerce' ); ?></h2>

			<div class="pin4wc-login-wrapper">

				<?php if ( empty( $args['token']['access_token'] ) ) : ?>

					<p><?php echo esc_html__( 'Login to connect with Pinterest APP.', 'pinterest-for-woocommerce' ); ?></p>

					<a href="<?php echo esc_url( Pinterest4WooCommerce()::get_service_login_url() ); ?>" class="button">
						<span class="dashicons dashicons-admin-network"></span>
						<?php echo esc_html__( 'Login to Pinterest', 'pinterest-for-woocommerce' ); ?>
					</a>

				<?php else : ?>

					<p class="pin4wc-success-text">
						<strong>
							<span class="dashicons dashicons-saved"></span>
							<?php
							// translators: Date
							echo esc_attr( sprintf( __( 'Connected to Pinterest APP on %s', 'pinterest-for-woocommerce' ), gmdate( 'M j, Y - H:i', ( $args['token']['issued_at'] / 1000 ) ) ) );
							?>
						</strong>
					</p>

					<div class="pin4wc-actions">

						<button type="button" class="button" data-action="revoke">
							<span class="dashicons dashicons-migrate"></span>
							<?php echo esc_html__( 'Revoke Permission', 'pinterest-for-woocommerce' ); ?>
						</button>

						<button type="button" class="button" data-action="refresh">
							<span class="dashicons dashicons-update-alt"></span>
							<?php echo esc_html__( 'Refresh Token', 'pinterest-for-woocommerce' ); ?>
						</button>

						<span id="pin4wcTokenResponse"></span>

					</div>

				<?php endif; ?>

				<hr/>

			</div>
			<p><button type="submit" class="button button-primary"><?php echo esc_html__( 'Save changes', 'pinterest-for-woocommerce' ); ?></button></p>

			<?php
			wp_nonce_field( $this->nonce_save_key, $this->nonce_save_key );
			submit_button( esc_html__( 'Save changes', 'pinterest-for-woocommerce' ) );

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
