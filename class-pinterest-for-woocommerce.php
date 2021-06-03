<?php
/**
 * Installation related functions and actions.
 *
 * @package  Pinterest_For_Woocommerce
 * @version  1.0.0
 */

use Automattic\WooCommerce\Pinterest as Pinterest;

if ( ! class_exists( 'Pinterest_For_Woocommerce' ) ) :

	/**
	 * Base Plugin class holding generic functionality
	 */
	final class Pinterest_For_Woocommerce {

		/**
		 * Pinterest_For_Woocommerce version.
		 *
		 * @var string
		 */
		public $version = '0.2.0';

		/**
		 * The single instance of the class.
		 *
		 * @var Pinterest_For_Woocommerce
		 * @since 1.0.0
		 */
		protected static $instance = null;

		/**
		 * The initialized state of the class.
		 *
		 * @var Pinterest_For_Woocommerce
		 * @since 1.0.0
		 */
		protected static $initialized = false;

		/**
		 * When set to true, the settings have been
		 * changed and the runtime cached must be flushed
		 *
		 * @var Pinterest_For_Woocommerce
		 * @since 1.0.0
		 */
		protected static $dirty_settings = false;

		/**
		 * The default settings that will be created
		 * with the given values, if they don't exist.
		 *
		 * @var Pinterest_For_Woocommerce
		 * @since 1.0.0
		 */
		protected static $default_settings = array(
			'track_conversions'      => true,
			'enhanced_match_support' => false,
			'save_to_pinterest'      => true,
			'rich_pins_on_posts'     => true,
			'rich_pins_on_products'  => true,
		);

		/**
		 * Main Pinterest_For_Woocommerce Instance.
		 *
		 * Ensures only one instance of Pinterest_For_Woocommerce is loaded or can be loaded.
		 *
		 * @since 1.0.0
		 * @static
		 * @see Pinterest_For_Woocommerce()
		 * @return Pinterest_For_Woocommerce - Main instance.
		 */
		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
				self::$instance->initalize_plugin();
			}
			return self::$instance;
		}

		/**
		 * Cloning is forbidden.
		 *
		 * @since 1.0.0
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'pinterest-for-woocommerce' ), '1.0.0' );
		}

		/**
		 * Unserializing instances of this class is forbidden.
		 *
		 * @since 1.0.0
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'pinterest-for-woocommerce' ), '1.0.0' );
		}

		/**
		 * Pinterest_For_Woocommerce Initializer.
		 */
		public function initalize_plugin() {
			if ( self::$initialized ) {
				_doing_it_wrong( __FUNCTION__, esc_html__( 'Only a single instance of this class is allowed. Use singleton.', 'pinterest-for-woocommerce' ), '1.0.0' );
				return;
			}

			self::$initialized = true;

			$this->define_constants();
			$this->includes();
			$this->init_hooks();

			do_action( 'pinterest_for_woocommerce_loaded' );
		}

		/**
		 * Define Pinterest_For_Woocommerce Constants.
		 */
		private function define_constants() {
			define( 'PINTEREST_FOR_WOOCOMMERCE_PREFIX', 'pinterest-for-woocommerce' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME', plugin_basename( PINTEREST_FOR_WOOCOMMERCE_PLUGIN_FILE ) );
			define( 'PINTEREST_FOR_WOOCOMMERCE_VERSION', $this->version );
			define( 'PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME', 'pinterest_for_woocommerce' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_LOG_PREFIX', 'pinterest-for-woocommerce' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_SETUP_GUIDE', PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-setup-guide' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_CATALOG_SYNC', PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-catalog-sync' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_WOO_CONNECT_URL', 'https://connect.woocommerce.com/' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_WOO_CONNECT_SERVICE', 'pinterestv3native' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_API_NAMESPACE', 'pinterest' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_API_VERSION', '1' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_API_AUTH_ENDPOINT', 'oauth/callback' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_AUTH', PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_auth_key' );
		}


		/**
		 * What type of request is this?
		 *
		 * @param  string $type admin, ajax, cron or frontend.
		 * @return bool
		 */
		private function is_request( $type ) {
			switch ( $type ) {
				case 'admin':
					return is_admin();
				case 'ajax':
					return defined( 'DOING_AJAX' );
				case 'cron':
					return defined( 'DOING_CRON' );
				case 'frontend':
					return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
			}
		}

		/**
		 * Include required core files used in admin and on the frontend.
		 */
		private function includes() {

			include_once 'includes/class-pinterest-for-woocommerce-install.php';

			if ( $this->is_request( 'admin' ) ) {
				include_once 'includes/admin/class-pinterest-for-woocommerce-admin.php';
			}

			if ( $this->is_request( 'frontend' ) ) {
				include_once 'includes/class-pinterest-for-woocommerce-frontend-assets.php';
			}
		}

		/**
		 * Hook into actions and filters.
		 *
		 * @since  1.0.0
		 */
		private function init_hooks() {
			add_action( 'init', array( $this, 'init' ), 0 );
			add_action( 'rest_api_init', array( $this, 'init_api_endpoints' ) );
			add_action( 'wp_head', array( $this, 'maybe_inject_verification_code' ) );
			add_action( 'wp_head', array( Pinterest\RichPins::class, 'maybe_inject_rich_pins_opengraph_tags' ) );
			add_action( 'wp', array( Pinterest\SaveToPinterest::class, 'maybe_init' ) );
			add_action( 'init', array( Pinterest\Tracking::class, 'maybe_init' ) );
			add_action( 'init', array( Pinterest\ProductSync::class, 'maybe_init' ) );
			add_action( 'pinterest_for_woocommerce_token_saved', array( $this, 'update_account_data' ) );
			add_action( 'pinterest_for_woocommerce_token_saved', array( $this, 'set_default_settings' ) );
		}

		/**
		 * Init Pinterest_For_Woocommerce when WordPress Initialises.
		 */
		public function init() {
			// Before init action.
			do_action( 'before_pinterest_for_woocommerce_init' );

			// Set up localisation.
			$this->load_plugin_textdomain();

			// Init action.
			do_action( 'pinterest_for_woocommerce_init' );
		}

		/**
		 * Load Localisation files.
		 *
		 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
		 *
		 * Locales found in:
		 *      - WP_LANG_DIR/pinterest-for-woocommerce/pinterest-for-woocommerce-LOCALE.mo
		 *      - WP_LANG_DIR/plugins/pinterest-for-woocommerce-LOCALE.mo
		 */
		private function load_plugin_textdomain() {
			$locale = apply_filters( 'plugin_locale', get_locale(), 'pinterest-for-woocommerce' );

			load_textdomain( 'pinterest-for-woocommerce', WP_LANG_DIR . '/pinterest-for-woocommerce/pinterest-for-woocommerce-' . $locale . '.mo' );
			load_plugin_textdomain( 'pinterest-for-woocommerce', false, plugin_basename( dirname( __FILE__ ) ) . '/i18n/languages' );
		}

		/**
		 * Get the plugin url.
		 *
		 * @return string
		 */
		public function plugin_url() {
			return untrailingslashit( plugins_url( '/', __FILE__ ) );
		}

		/**
		 * Get the plugin path.
		 *
		 * @return string
		 */
		public function plugin_path() {
			return untrailingslashit( plugin_dir_path( __FILE__ ) );
		}

		/**
		 * Get the template path.
		 *
		 * @return string
		 */
		public function template_path() {
			return apply_filters( 'pinterest_for_woocommerce_template_path', 'pinterest-for-woocommerce/' );
		}

		/**
		 * Get Ajax URL.
		 *
		 * @return string
		 */
		public function ajax_url() {
			return admin_url( 'admin-ajax.php', 'relative' );
		}



		/**
		 * Return APP Settings
		 *
		 * @since 1.0.0
		 *
		 * @param boolean $force Controls whether to force getting a fresh value instead of one from the runtime cache.
		 * @return array
		 */
		public static function get_settings( $force = false ) {

			static $settings;

			if ( is_null( $settings ) || $force || self::$dirty_settings ) {
				$settings = get_option( PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME );
			}

			return $settings;
		}


		/**
		 * Return APP Setting based on its key
		 *
		 * @since 1.0.0
		 *
		 * @param string  $key The key of specific option to retrieve.
		 * @param boolean $force Controls whether to force getting a fresh value instead of one from the runtime cache.
		 *
		 * @return mixed
		 */
		public static function get_setting( $key, $force = false ) {

			$settings = self::get_settings( $force );

			return empty( $settings[ $key ] ) ? false : $settings[ $key ];
		}


		/**
		 * Save APP Setting
		 *
		 * @since 1.0.0
		 *
		 * @param string $key The key of specific option to retrieve.
		 * @param mixed  $data The data to save for this option key.
		 *
		 * @return boolean
		 */
		public static function save_setting( $key, $data ) {

			$settings = self::get_settings( true );

			$settings[ $key ] = $data;

			return self::save_settings( $settings );
		}


		/**
		 * Save APP Settings
		 *
		 * @since 1.0.0
		 *
		 * @param array $settings The array of settings to save.
		 *
		 * @return boolean
		 */
		public static function save_settings( $settings ) {
			self::$dirty_settings = true;
			return update_option( PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME, $settings );
		}

		/**
		 * Add API endpoints
		 *
		 * @since 1.0.0
		 */
		public function init_api_endpoints() {
			new Pinterest\API\Auth();
			new Pinterest\API\DomainVerification();
			new Pinterest\API\Advertisers();
			new Pinterest\API\Tags();
			new Pinterest\API\FeedState();
			new Pinterest\API\FeedIssues();
		}

		/**
		 * Get decripted token data
		 *
		 * @since 1.0.0
		 *
		 * @return array
		 */
		public static function get_token() {

			$token = self::get_setting( 'token', true );

			try {
				$token['access_token'] = empty( $token['access_token'] ) ? '' : Pinterest\Crypto::decrypt( $token['access_token'] );
			} catch ( \Exception $th ) {
				/* Translators: The error description */
				Pinterest\Logger::log( sprintf( esc_html__( 'Could not decrypt the Pinterest API access token. Try reconnecting to Pinterest. [%s]', 'pinterest-for-woocommerce' ), $th->getMessage() ), 'error' );
				$token = array();
			}

			return $token;
		}


		/**
		 * Save encripted token data
		 *
		 * @since 1.0.0
		 *
		 * @param array $token The array containing the token values to save.
		 *
		 * @return boolean
		 */
		public static function save_token( $token ) {

			$token['access_token'] = empty( $token['access_token'] ) ? '' : Pinterest\Crypto::encrypt( $token['access_token'] );
			return self::save_setting( 'token', $token );
		}


		/**
		 * Clear the token
		 *
		 * @since 1.0.0
		 *
		 * @return boolean
		 */
		public static function clear_token() {
			return self::save_token( array() );
		}


		/**
		 * Return WooConnect Bridge URL
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public static function get_connection_proxy_url() {

			/**
			 * Filters the proxy URL.
			 *
			 * @since 1.0.0
			 *
			 * @param string $proxy_url the connection proxy URL
			 */
			return (string) trailingslashit( apply_filters( 'pinterest_for_woocommerce_connection_proxy_url', PINTEREST_FOR_WOOCOMMERCE_WOO_CONNECT_URL ) );
		}


		/**
		 * Return Service Login URL
		 *
		 * @since 1.0.0
		 *
		 * @param string $view The context view parameter.
		 *
		 * @return string
		 */
		public static function get_service_login_url( $view = null ) {

			$control_key = uniqid();
			$view        = is_null( $view ) ? 'settings' : $view;
			$state       = http_build_query(
				array(
					'redirect' => get_rest_url( null, PINTEREST_FOR_WOOCOMMERCE_API_NAMESPACE . '/v' . PINTEREST_FOR_WOOCOMMERCE_API_VERSION . '/' . PINTEREST_FOR_WOOCOMMERCE_API_AUTH_ENDPOINT ) . '?control=' . $control_key . '&view=' . $view,
				)
			);

			set_transient( PINTEREST_FOR_WOOCOMMERCE_AUTH, $control_key, MINUTE_IN_SECONDS * 5 );

			return self::get_connection_proxy_url() . 'login/' . PINTEREST_FOR_WOOCOMMERCE_WOO_CONNECT_SERVICE . '?' . $state;
		}



		/**
		 * Injects needed meta tags to the site's header
		 *
		 * @since 1.0.0
		 */
		public function maybe_inject_verification_code() {

			if ( self::get_setting( 'verfication_code' ) ) {
				printf( '<meta name="p:domain_verify" content="%s"/>', esc_attr( self::get_setting( 'verfication_code' ) ) );
			}
		}


		/**
		 * Fetches the account_data parameters from Pinterest's API
		 * Saves it to the plugin options and returns it.
		 *
		 * @since 1.0.0
		 *
		 * @return array() account_data from Pinterest
		 */
		public static function update_account_data() {

			$account_data = Pinterest\API\Base::get_account_info();

			if ( 'success' === $account_data['status'] ) {

				$data = array_intersect_key(
					(array) $account_data['data'],
					array(
						'verified_domains' => '',
						'domain_verified'  => '',
						'username'         => '',
						'id'               => '',
						'image_medium_url' => '',
					)
				);

				Pinterest_For_Woocommerce()::save_setting( 'account_data', $data );
				return $data;
			}

			return array();

		}


		/**
		 * Returns the Pinterest AccountID from the database.
		 *
		 * @return string|false
		 */
		public static function get_account_id() {
			$account_data = Pinterest_For_Woocommerce()::get_setting( 'account_data' );
			return isset( $account_data['id'] ) ? $account_data['id'] : false;
		}


		/**
		 * Sets the default settings based on the
		 * given values in self::$default_settings
		 *
		 * @return boolean
		 */
		public static function set_default_settings() {

			$settings = self::get_settings( true );
			$settings = wp_parse_args( $settings, self::$default_settings );

			return self::save_settings( $settings );

		}


		/**
		 * Checks whether we have verified our domain, by checking account_data as
		 * returned by Pinterest.
		 *
		 * @return boolean
		 */
		public static function is_domain_verified() {
			$account_data = Pinterest_For_Woocommerce()::get_setting( 'account_data' );
			return isset( $account_data['domain_verified'] ) ? (bool) $account_data['domain_verified'] : false;
		}


		/**
		 * Checks if tracking is configured properly and enabled.
		 *
		 * @return boolean
		 */
		public static function is_tracking_enabled() {
			return false !== Pinterest\Tracking::get_active_tag();
		}
	}

endif;
