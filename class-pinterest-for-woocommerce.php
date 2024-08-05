<?php
/**
 * Installation related functions and actions.
 *
 * @package  Pinterest_For_Woocommerce
 * @version  1.0.0
 */

use Automattic\WooCommerce\Grow\Tools\CompatChecker\v0_0_1\Checker;
use Automattic\WooCommerce\Admin\Features\OnboardingTasks\TaskLists;
use Automattic\WooCommerce\Admin\Notes\NotesUnavailableException;
use Automattic\WooCommerce\Pinterest;
use Automattic\WooCommerce\Pinterest\AdCredits;
use Automattic\WooCommerce\Pinterest\AdCreditsCoupons;
use Automattic\WooCommerce\Pinterest\AdsCreditCurrency;
use Automattic\WooCommerce\Pinterest\Admin\Tasks\Onboarding;
use Automattic\WooCommerce\Pinterest\API\UserInteraction;
use Automattic\WooCommerce\Pinterest\Billing;
use Automattic\WooCommerce\Pinterest\FeedRegistration;
use Automattic\WooCommerce\Pinterest\Heartbeat;
use Automattic\WooCommerce\Pinterest\Logger;
use Automattic\WooCommerce\Pinterest\Notes\MarketingNotifications;
use Automattic\WooCommerce\Pinterest\Notes\TokenExchangeFailure;
use Automattic\WooCommerce\Pinterest\Notes\TokenInvalidFailure;
use Automattic\WooCommerce\Pinterest\PinterestApiException;
use Automattic\WooCommerce\Pinterest\ProductFeedStatus;
use Automattic\WooCommerce\Pinterest\Tracking;
use Automattic\WooCommerce\Pinterest\Tracking\Conversions;
use Automattic\WooCommerce\Pinterest\Tracking\Data\User;
use Automattic\WooCommerce\Pinterest\Tracking\Tag;
use Automattic\WooCommerce\Pinterest\Utilities\Tracks;

if ( ! class_exists( 'Pinterest_For_Woocommerce' ) ) :

	/**
	 * Base Plugin class holding generic functionality
	 */
	final class Pinterest_For_Woocommerce {

		use Tracks;

		/**
		 * Tos IDs and URLs per country.
		 */
		const TOS_PER_COUNTRY = array(
			'US' => array(
				'tos_id'    => 8,
				'terms_url' => 'https://business.pinterest.com/en/pinterest-advertising-services-agreement',
			),
			'CA' => array(
				'tos_id'    => 8,
				'terms_url' => 'https://business.pinterest.com/en/pinterest-advertising-services-agreement',
			),
			'FR' => array(
				'tos_id'    => 11,
				'terms_url' => 'https://business.pinterest.com/fr/pinterest-advertising-services-agreement',
			),
			'BR' => array(
				'tos_id'    => 15,
				'terms_url' => 'https://business.pinterest.com/pt-br/pinterest-advertising-services-agreement/',
			),
			'MX' => array(
				'tos_id'    => 16,
				'terms_url' => 'https://business.pinterest.com/es/pinterest-advertising-services-agreement/mexico/',
			),
			'*'  => array(
				'tos_id'    => 9,
				'terms_url' => 'https://business.pinterest.com/en-gb/pinterest-advertising-services-agreement/',
			),
		);

		/**
		 * Set the minimum required versions for the plugin.
		 */
		const PLUGIN_REQUIREMENTS = array(
			'php_version'      => '7.4',
			'wp_version'       => '5.6',
			'wc_version'       => '5.3',
			'action_scheduler' => '3.3.0',
		);

		/**
		 * Pinterest_For_Woocommerce version.
		 *
		 * @var string
		 */
		public $version = PINTEREST_FOR_WOOCOMMERCE_VERSION;

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
		 * Heartbeat instance.
		 *
		 * @var Heartbeat
		 * @since 1.1.0
		 */
		protected $heartbeat = null;

		/**
		 * When set to true, the settings have been
		 * changed and the runtime cached must be flushed
		 *
		 * @var Pinterest_For_Woocommerce
		 * @since 1.0.0
		 */
		protected static $dirty_settings = array();

		/**
		 * The default settings that will be created
		 * with the given values, if they don't exist.
		 *
		 * @var array
		 * @since 1.0.0
		 */
		protected static $default_settings = array(
			'track_conversions'                => true,
			'track_conversions_capi'           => false,
			'enhanced_match_support'           => true,
			'automatic_enhanced_match_support' => true,
			'save_to_pinterest'                => true,
			'rich_pins_on_posts'               => true,
			'rich_pins_on_products'            => true,
			'product_sync_enabled'             => true,
			'enable_debug_logging'             => false,
			'erase_plugin_data'                => false,
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
				self::$instance->maybe_init_plugin();
			}
			return self::$instance;
		}

		/**
		 * Cloning is forbidden.
		 *
		 * @since 1.0.0
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning this class is forbidden.', 'pinterest-for-woocommerce' ), '1.0.0' );
		}

		/**
		 * Deserializing instances of this class is forbidden.
		 *
		 * @since 1.0.0
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Deserializing instances of this class is forbidden.', 'pinterest-for-woocommerce' ), '1.0.0' );
		}

		/**
		 * Pinterest_For_Woocommerce Initializer.
		 */
		public function maybe_init_plugin() {
			if ( self::$initialized ) {
				_doing_it_wrong( __FUNCTION__, esc_html__( 'Only a single instance of this class is allowed. Use singleton.', 'pinterest-for-woocommerce' ), '1.0.0' );
				return;
			}

			self::$initialized = true;

			$this->define_constants();

			add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );

			/**
			 * Plugin loaded action.
			 * phpcs:disable WooCommerce.Commenting.CommentHooks.MissingSinceComment
			 */
			do_action( 'pinterest_for_woocommerce_loaded' );
		}


		/**
		 * Define Pinterest_For_Woocommerce Constants.
		 */
		private function define_constants() {
			define( 'PINTEREST_FOR_WOOCOMMERCE_PREFIX', 'pinterest-for-woocommerce' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME', plugin_basename( PINTEREST_FOR_WOOCOMMERCE_PLUGIN_FILE ) );
			define( 'PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME', 'pinterest_for_woocommerce' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_DATA_NAME', 'pinterest_for_woocommerce_data' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_LOG_PREFIX', 'pinterest-for-woocommerce' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_WOO_CONNECT_URL', 'https://api.woocommerce.com/' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_WOO_CONNECT_SERVICE', 'pinterest-v5' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_API_NAMESPACE', 'pinterest' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_CONNECT_NONCE', 'wp_rest' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_API_VERSION', '1' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_API_AUTH_ENDPOINT', 'oauth/callback' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX', 'pfw' );
			define( 'PINTEREST_FOR_WOOCOMMERCE_PINTEREST_API_VERSION', PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME . '_pinterest_api_version' );
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
				default:
					return false;
			}
		}

		/**
		 * Include required core files used in admin and on the frontend.
		 */
		private function includes() {

			include_once 'includes/class-pinterest-for-woocommerce-ads-supported-countries.php';

			if ( $this->is_request( 'admin' ) ) {
				include_once 'includes/admin/class-pinterest-for-woocommerce-admin.php';
			}

			if ( $this->is_request( 'frontend' ) ) {
				include_once 'includes/class-pinterest-for-woocommerce-frontend-assets.php';
			}
		}

		/**
		 * Include plugins files and hook into actions and filters.
		 *
		 * @since  1.0.0
		 */
		public function init_plugin() {

			if ( ! Checker::instance()->is_compatible( PINTEREST_FOR_WOOCOMMERCE_PLUGIN_FILE, PINTEREST_FOR_WOOCOMMERCE_VERSION ) || ! $this->check_plugin_requirements() ) {
				return;
			}

			$this->includes();

			add_action( 'admin_init', array( $this, 'admin_init' ), 0 );
			add_action( 'rest_api_init', array( $this, 'init_api_endpoints' ) );
			add_action( 'wp_head', array( $this, 'maybe_inject_verification_code' ) );
			add_action( 'wp_head', array( Pinterest\RichPins::class, 'maybe_inject_rich_pins_opengraph_tags' ) );
			add_action( 'wp', array( Pinterest\SaveToPinterest::class, 'maybe_init' ) );

			add_action( 'init', array( $this, 'init' ), 0 );

			// ActionScheduler is activated on init 1 so lets make sure we are updating after that.
			add_action( 'init', array( $this, 'maybe_update_plugin' ), 5 );
			add_action( 'init', array( self::class, 'init_tracking' ) );
			add_action( 'init', array( Pinterest\Heartbeat::class, 'schedule_events' ) );
			add_action( 'init', array( Pinterest\ProductSync::class, 'maybe_init' ) );
			add_action( 'init', array( Pinterest\TrackerSnapshot::class, 'maybe_init' ) );
			add_action( 'init', array( Pinterest\Billing::class, 'schedule_event' ) );
			add_action( 'init', array( Pinterest\AdCredits::class, 'schedule_event' ) );
			add_action( 'init', array( Pinterest\RefreshToken::class, 'schedule_event' ) );

			// Register the marketing channel if the feature is included.
			if ( defined( 'WC_MCM_EXISTS' ) ) {
				add_action(
					'init',
					array( Pinterest\MultichannelMarketing\MarketingChannelRegistrar::class, 'register' )
				);
			}

			// Verify that the ads_campaign is active or not.
			add_action( 'admin_init', array( Pinterest\AdCredits::class, 'check_if_ads_campaign_is_active' ) );

			// Append credits info to account data.
			add_action( 'init', array( $this, 'add_currency_credits_info_to_account_data' ) );

			add_action( 'pinterest_for_woocommerce_token_saved', array( self::class, 'set_default_settings' ) );
			add_action( 'pinterest_for_woocommerce_token_saved', array( self::class, 'create_commerce_integration' ) );
			add_action( 'pinterest_for_woocommerce_token_saved', array( self::class, 'update_account_data' ) );
			add_action( 'pinterest_for_woocommerce_token_saved', array( self::class, 'update_linked_businesses' ) );
			add_action( 'pinterest_for_woocommerce_token_saved', array( self::class, 'post_update_cleanup' ) );
			add_action( 'pinterest_for_woocommerce_token_saved', array( TokenInvalidFailure::class, 'possibly_delete_note' ) );

			add_action( 'pinterest_for_woocommerce_disconnect', array( self::class, 'reset_connection' ) );

			add_action( 'action_scheduler_failed_execution', array( self::class, 'action_scheduler_reset_connection' ), 10, 2 );

			// Handle the Pinterest verification URL.
			add_action( 'parse_request', array( $this, 'verification_request' ) );

			// Init marketing notifications.
			add_action( Heartbeat::DAILY, array( $this, 'init_marketing_notifications' ) );

			// Hook the setup task. The hook admin_init is not triggered when the WC fetches the tasks using the endpoint: wp-json/wc-admin/onboarding/tasks and hence hooking into init.
			add_action( 'init', array( $this, 'add_onboarding_task' ), 20 );
		}

		/**
		 * Initialize Tracker and add trackers to it.
		 *
		 * @since 1.4.0
		 *
		 * @return Pinterest\Tracking|false
		 */
		public static function init_tracking() {
			/**
			 * Filters whether to disable tracking.
			 *
			 * @since 1.4.0
			 *
			 * @param bool $disable_tracking Whether to disable tracking.
			 */
			$is_tracking_disabled             = apply_filters( 'woocommerce_pinterest_disable_tracking', false );
			$is_tracking_conversions_disabled = ! Pinterest_For_Woocommerce()::get_setting( 'track_conversions' );
			$is_not_a_site                    = wp_doing_cron() || is_admin();

			if ( $is_tracking_disabled || $is_tracking_conversions_disabled || $is_not_a_site ) {
				return false;
			}

			$is_tracking_conversions_capi_enabled = Pinterest_For_Woocommerce()::get_setting( 'track_conversions_capi' );

			$tracking = new Tracking( array( new Tag() ) );

			if ( $is_tracking_conversions_capi_enabled ) {
				$user                = new User( WC_Geolocation::get_ip_address(), wc_get_user_agent() );
				$conversions_tracker = new Conversions( $user );
				$tracking->add_tracker( $conversions_tracker );
			}

			return $tracking;
		}

		/**
		 * Init Pinterest_For_Woocommerce when WordPress initializes.
		 */
		public function init() {
			/**
			 * Before init action.
			 * phpcs:disable WooCommerce.Commenting.CommentHooks.MissingSinceComment
			 */
			do_action( 'before_pinterest_for_woocommerce_init' );

			// Set up localization.
			$this->load_plugin_textdomain();

			/**
			 * Init action.
			 * phpcs:disable WooCommerce.Commenting.CommentHooks.MissingSinceComment
			 */
			do_action( 'pinterest_for_woocommerce_init' );
		}

		/**
		 * Init classes for admin interface.
		 */
		public function admin_init() {
			$view_factory         = new Pinterest\View\PHPViewFactory();
			$admin                = new Pinterest\Admin\Admin( $view_factory );
			$attributes_tab       = new Pinterest\Admin\Product\Attributes\AttributesTab( $admin );
			$activation_redirect  = new Pinterest\Admin\ActivationRedirect();
			$variation_attributes = new Pinterest\Admin\Product\Attributes\VariationsAttributes( $admin );

			$admin->register();
			$attributes_tab->register();
			$activation_redirect->register();
			$variation_attributes->register();
		}

		/**
		 * Init marketing notifications.
		 *
		 * @since 1.1.0
		 */
		public function init_marketing_notifications() {
			$notifications = new MarketingNotifications();
			$notifications->init_notifications();
		}

		/**
		 * Checks all plugin requirements. If run in admin context also adds a notice.
		 *
		 * @return boolean
		 */
		public function check_plugin_requirements() {

			$errors = array();
			global $wp_version;

			if ( ! version_compare( PHP_VERSION, self::PLUGIN_REQUIREMENTS['php_version'], '>=' ) ) {
				/* Translators: The minimum PHP version */
				$errors[] = sprintf( esc_html__( 'Pinterest for WooCommerce requires a minimum PHP version of %s.', 'pinterest-for-woocommerce' ), self::PLUGIN_REQUIREMENTS['php_version'] );
			}

			if ( ! version_compare( $wp_version, self::PLUGIN_REQUIREMENTS['wp_version'], '>=' ) ) {
				/* Translators: The minimum WP version */
				$errors[] = sprintf( esc_html__( 'Pinterest for WooCommerce requires a minimum WordPress version of %s.', 'pinterest-for-woocommerce' ), self::PLUGIN_REQUIREMENTS['wp_version'] );
			}

			if ( ! defined( 'WC_VERSION' ) || ! version_compare( WC_VERSION, self::PLUGIN_REQUIREMENTS['wc_version'], '>=' ) ) {
				/* Translators: The minimum WC version */
				$errors[] = sprintf( esc_html__( 'Pinterest for WooCommerce requires a minimum WooCommerce version of %s.', 'pinterest-for-woocommerce' ), self::PLUGIN_REQUIREMENTS['wc_version'] );
			}

			/**
			 * Check if WooCommerce Admin is enabled.
			 * phpcs:disable WooCommerce.Commenting.CommentHooks.MissingSinceComment
			 */
			if ( apply_filters( 'woocommerce_admin_disabled', false ) ) {
				$errors[] = esc_html__( 'Pinterest for WooCommerce requires WooCommerce Admin to be enabled.', 'pinterest-for-woocommerce' );
			}

			if ( ! function_exists( 'as_has_scheduled_action' ) ) {
				/* Translators: The minimum Action Scheduler version */
				$errors[] = sprintf( esc_html__( 'Pinterest for WooCommerce requires a minimum Action Scheduler package of %s. It can be caused by old version of the WooCommerce extensions.', 'pinterest-for-woocommerce' ), self::PLUGIN_REQUIREMENTS['action_scheduler'] );
			}

			if ( empty( $errors ) ) {
				return true;
			}

			if ( $this->is_request( 'admin' ) ) {
				add_action(
					'admin_notices',
					function () use ( $errors ) {
						?>
						<div class="notice notice-error">
							<?php
							foreach ( $errors as $error ) {
								echo '<p>' . esc_html( $error ) . '</p>';
							}
							?>
						</div>
						<?php
					}
				);
			}

			return false;
		}

		/**
		 * Plugin update entry point.
		 *
		 * @since 1.0.9
		 * @return void
		 */
		public function maybe_update_plugin() {
			$plugin_update = new Pinterest\PluginUpdate();
			$plugin_update->maybe_update();
		}

		/**
		 * Load localization files.
		 *
		 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
		 *
		 * Locales found in:
		 *      - WP_LANG_DIR/pinterest-for-woocommerce/pinterest-for-woocommerce-LOCALE.mo
		 *      - WP_LANG_DIR/plugins/pinterest-for-woocommerce-LOCALE.mo
		 */
		private function load_plugin_textdomain() {
			/**
			 * Get plugin locale.
			 * phpcs:disable WooCommerce.Commenting.CommentHooks.MissingSinceComment
			 */
			$locale = apply_filters( 'plugin_locale', get_locale(), 'pinterest-for-woocommerce' );

			load_textdomain( 'pinterest-for-woocommerce', WP_LANG_DIR . '/pinterest-for-woocommerce/pinterest-for-woocommerce-' . $locale . '.mo' );
			load_plugin_textdomain( 'pinterest-for-woocommerce', false, plugin_basename( __DIR__ ) . '/i18n/languages' );
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
			/**
			 * Returns template path.
			 * phpcs:disable WooCommerce.Commenting.CommentHooks.MissingSinceComment
			 */
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
		 * @param boolean $force  Controls whether to force getting a fresh value instead of one from the runtime cache.
		 * @param string  $option Controls which option to read/write to.
		 *
		 * @return array
		 */
		public static function get_settings( $force = false, $option = PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME ) {

			static $settings;

			if ( $force || is_null( $settings ) || ! isset( $settings[ $option ] ) || ( isset( self::$dirty_settings[ $option ] ) && self::$dirty_settings[ $option ] ) ) {
				$settings[ $option ] = get_option( $option );
			}

			return $settings[ $option ];
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
			// Handle possible false value.
			if ( ! is_array( $settings ) ) {
				$settings = array();
			}
			$settings[ $key ] = $data;

			return self::save_settings( $settings );
		}


		/**
		 * Save APP Settings
		 *
		 * @since 1.0.0
		 *
		 * @param array  $settings The array of settings to save.
		 * @param string $option Controls which option to read/write to.
		 *
		 * @return boolean
		 */
		public static function save_settings( $settings, $option = PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME ) {
			self::$dirty_settings[ $option ] = true;
			return update_option( $option, $settings );
		}

		/**
		 * Set API version used by the plugin.
		 *
		 * @since 1.4.0
		 *
		 * @param string $version The API version.
		 *
		 * @return boolean
		 */
		public static function set_api_version( $version ) {
			return update_option( PINTEREST_FOR_WOOCOMMERCE_PINTEREST_API_VERSION, $version );
		}

		/**
		 * Get API version used by the plugin.
		 *
		 * @since 1.4.0
		 *
		 * @return string The API version.
		 */
		public static function get_api_version() {
			return get_option( PINTEREST_FOR_WOOCOMMERCE_PINTEREST_API_VERSION, '' );
		}

		/**
		 * Return APP Data based on its key
		 *
		 * @since 1.0.0
		 *
		 * @param string  $key The key of specific data to retrieve.
		 * @param boolean $force Controls whether to force getting a fresh value instead of one from the runtime cache.
		 *
		 * @return mixed
		 */
		public static function get_data( $key, $force = false ) {

			$settings = self::get_settings( $force, PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );

			return $settings[ $key ] ?? null;
		}


		/**
		 * Save APP Data
		 *
		 * @since 1.0.0
		 *
		 * @param string $key The key of specific data to retrieve.
		 * @param mixed  $data The data to save for this option key.
		 *
		 * @return boolean
		 */
		public static function save_data( $key, $data ) {

			$settings = self::get_settings( true, PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );
			// Handle possible false value.
			if ( ! is_array( $settings ) ) {
				$settings = array();
			}
			$settings[ $key ] = $data;

			return self::save_settings( $settings, PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );
		}

		/**
		 * Remove APP Data key.
		 *
		 * @param string $key - The key of specific data to retrieve.
		 *
		 * @since 1.3.1
		 *
		 * @return bool - True if the data was removed, false otherwise.
		 */
		public static function remove_data( string $key ) {
			$settings = self::get_settings( true, PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );
			unset( $settings[ $key ] );
			return self::save_settings( $settings, PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );
		}

		/**
		 * Add API endpoints
		 *
		 * @since 1.0.0
		 */
		public function init_api_endpoints() {
			new Pinterest\API\Advertisers();
			new Pinterest\API\AdvertiserConnect();
			new Pinterest\API\Auth();
			new Pinterest\API\AuthDisconnect();
			new Pinterest\API\Businesses();
			new Pinterest\API\DomainVerification();
			new Pinterest\API\FeedState();
			new Pinterest\API\FeedIssues();
			new Pinterest\API\Tags();
			new Pinterest\API\Health();
			new Pinterest\API\Settings();
			new Pinterest\API\SyncSettings();
			new Pinterest\API\UserInteraction();
		}

		/**
		 * Get decrypted token data.
		 *
		 * The Access token and Crypto key live in the data option in the following form:
		 * data: {
		 *   ...
		 *   token: {
		 *     access_token: ${encrypted_token},
		 *   },
		 *   crypto_encoded_key: ${encryption_key},
		 *   ...
		 * }
		 *
		 * @since 1.0.0
		 *
		 * @return array
		 */
		public static function get_access_token() {

			$token_data = self::get_data( 'token_data', true );
			$token      = array();

			try {
				$token['access_token'] = empty( $token_data['access_token'] ) ? '' : Pinterest\Crypto::decrypt( $token_data['access_token'] );
			} catch ( \Exception $th ) {
				/* Translators: The error description */
				Logger::log( sprintf( esc_html__( 'Could not decrypt the Pinterest API access token. Try reconnecting to Pinterest. [%s]', 'pinterest-for-woocommerce' ), $th->getMessage() ), 'error' );
			}

			return $token;
		}


		/**
		 * Save encrypted token data. See the documentation of the get_token() method for the expected format of the related data variables.
		 *
		 * @since 1.0.0
		 * @since 1.4.0 Added refresh token and tokens expiration.
		 *
		 * @param array $token The array containing the token values to save.
		 *
		 * @return boolean
		 */
		public static function save_token_data( $token ) {
			$token['access_token']             = empty( $token['access_token'] ) ? '' : Pinterest\Crypto::encrypt( $token['access_token'] );
			$token['expires_in']               = $token['expires_in'] ?? '';
			$token['refresh_token']            = empty( $token['refresh_token'] ) ? '' : Pinterest\Crypto::encrypt( $token['refresh_token'] );
			$token['refresh_token_expires_in'] = $token['refresh_token_expires_in'] ?? '';
			$token['scopes']                   = empty( $token['scopes'] ) ? '' : $token['scopes'];
			$token['refresh_time']             = time();

			return self::save_data( 'token_data', $token );
		}

		/**
		 * Save connection info data.
		 *
		 * @since 1.4.0
		 *
		 * @param array $connection_info_data The array containing the connection info data.
		 * @return bool True if the data was saved successfully.
		 */
		public static function save_connection_info_data( array $connection_info_data ): bool {
			return self::save_data( 'connection_info_data', $connection_info_data );
		}

		/**
		 * Saves the integration data.
		 *
		 * @param array $integration_data The array containing the integration data.
		 * @return bool True if the data was saved successfully.
		 */
		public static function save_integration_data( array $integration_data ): bool {
			return self::save_data( 'integration_data', $integration_data );
		}

		/**
		 * Disconnect by clearing the Token and any other data that we should gather from scratch.
		 *
		 * @since 1.0.0
		 *
		 * @return bool True if disconnection was successful.
		 *
		 * @throws Exception PHP Exception.
		 */
		public static function disconnect(): bool {
			// Reset Feed file generation telemetry.
			ProductFeedStatus::deregister();
			/*
			 * If there is no business connected, disconnecting merchant will throw error.
			 * Just need to clean account data in these cases.
			 */
			if ( ! self::is_business_connected() ) {
				self::flush_options();
				// At this point we're disconnected.
				return true;
			}

			try {
				// Delete all the feeds for the merchant.
				FeedRegistration::maybe_delete_stale_feeds_for_merchant( '' );
				// Disconnect merchant from Pinterest.
				self::delete_commerce_integration();
				self::flush_options();
				// At this point we're disconnected.
				return true;
			} catch ( Exception $th ) {
				// There was an error disconnecting merchant.
				return false;
			}
		}

		/**
		 * Resets the connection by clearing the local connection data.
		 *
		 * @since 1.4.4
		 *
		 * @return void
		 * @throws \Automattic\WooCommerce\Admin\Notes\NotesUnavailableException If the notes API is not available.
		 */
		public static function reset_connection() {
			self::save_data( 'integration_data', array() );
			self::disconnect();

			TokenInvalidFailure::possibly_add_note();
		}

		/**
		 * Resets the connection from action scheduler.
		 *
		 * @since 1.4.4
		 *
		 * @param string    $action_id The ID of the action.
		 * @param Exception $e         The exception that was thrown.
		 *
		 * @return void
		 * @throws NotesUnavailableException If the notes API is not available.
		 * @throws Exception                 If the exception is a 401 error.
		 */
		public static function action_scheduler_reset_connection( $action_id, $e ) {
			if ( in_array( $e->getCode(), array( 401, 403 ) ) ) {
				self::reset_connection();
				throw $e;
			}
		}

		/**
		 * Flush data option and remove settings.
		 *
		 * @return void
		 */
		private static function flush_options() {

			// Flush the whole data option.
			delete_option( PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );
			UserInteraction::flush_options();

			// Remove settings that may cause issues if stale on disconnect.
			self::save_setting( 'integration_data', array() );
			self::save_setting( 'account_data', null );
			self::save_setting( 'tracking_advertiser', null );
			self::save_setting( 'tracking_tag', null );

			// Cancel scheduled jobs.
			Pinterest\ProductSync::cancel_jobs();
			Heartbeat::cancel_jobs();
		}

		/**
		 * Return WooConnect Bridge URL
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public static function get_connection_proxy_url() {
			return (string) trailingslashit(
				/**
				 * Filters the proxy URL.
				 *
				 * @since 1.0.0
				 *
				 * @param string $proxy_url the connection proxy URL
				 */
				apply_filters(
					'pinterest_for_woocommerce_connection_proxy_url',
					PINTEREST_FOR_WOOCOMMERCE_WOO_CONNECT_URL
				)
			);
		}


		/**
		 * Return The Middleware URL based on the given context
		 *
		 * @since 1.0.0
		 *
		 * @param string $context The context parameter.
		 * @param string $args    Additional arguments like 'view' or 'business_id'.
		 *
		 * @return string
		 */
		public static function get_middleware_url( $context = 'login', $args = array() ) {

			$nonce = wp_create_nonce( PINTEREST_FOR_WOOCOMMERCE_CONNECT_NONCE );
			set_transient( PINTEREST_FOR_WOOCOMMERCE_CONNECT_NONCE, $nonce, 10 * MINUTE_IN_SECONDS );

			$rest_url = get_rest_url( null, PINTEREST_FOR_WOOCOMMERCE_API_NAMESPACE . '/v' . PINTEREST_FOR_WOOCOMMERCE_API_VERSION . '/' . PINTEREST_FOR_WOOCOMMERCE_API_AUTH_ENDPOINT );

			$state_params = array(
				'redirect' => $rest_url,
				'nonce'    => $nonce,
			);

			switch ( $context ) {
				case 'create_business':
					$state_params['create-business'] = true;
					break;
				case 'switch_business':
					$state_params['switch-to-business'] = $args['business_id'];
					break;
			}

			$state = http_build_query( $state_params );

			// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
			// nosemgrep: audit.php.wp.security.xss.query-arg
			return self::get_connection_proxy_url() . 'integrations/connect/' . PINTEREST_FOR_WOOCOMMERCE_WOO_CONNECT_SERVICE . '?' . $state;
		}


		/**
		 * Injects needed meta tags to the site's header
		 *
		 * @since 1.0.0
		 */
		public function maybe_inject_verification_code() {

			$verification_data = self::get_data( 'verification_data' );

			if ( $verification_data ) {
				printf( '<meta name="p:domain_verify" content="%s"/>', esc_attr( $verification_data['verification_code'] ) );
			}
		}

		/**
		 * Connects WC to Pinterest.
		 *
		 * @return array the result of APIV5::create_commerce_integration.
		 * @throws Exception In case of 404, 409 and 500 errors from Pinterest.
		 * @see Pinterest\API\APIV5::create_commerce_integration
		 * @since 1.4.0
		 */
		public static function create_commerce_integration(): array {
			global $wp_version;

			$external_business_id = self::generate_external_business_id();
			$connection_data      = self::get_data( 'connection_info_data', true );

			// It does not make any sense to create integration without Advertiser ID.
			if ( empty( $connection_data['advertiser_id'] ) ) {
				throw new Exception(
					sprintf(
						esc_html__(
							'Commerce Integration cannot be created: Advertiser ID is missing.',
							'pinterest-for-woocommerce'
						)
					)
				);
			}

			$integration_data = array(
				'external_business_id'    => $external_business_id,
				'connected_merchant_id'   => $connection_data['merchant_id'] ?? '',
				'connected_advertiser_id' => $connection_data['advertiser_id'] ?? '',
				'partner_metadata'        => json_encode(
					array(
						'plugin_version' => PINTEREST_FOR_WOOCOMMERCE_VERSION,
						'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
						'wp_version'     => $wp_version,
						'locale'         => get_locale(),
						'currency'       => get_woocommerce_currency(),
					)
				),
			);

			if ( ! empty( $connection_data['tag_id'] ) ) {
				$integration_data['connected_tag_id'] = $connection_data['tag_id'];
			}

			$response = Pinterest\API\APIV5::create_commerce_integration( $integration_data );

			/*
			 * In case of successful response we save our integration data into a database.
			 * Data we save includes but not limited to:
			 *  external business id,
			 *  id,
			 *  connected_user_id,
			 *  etc.
			 */
			self::save_integration_data( $response );

			self::save_setting( 'tracking_advertiser', $response['connected_advertiser_id'] );
			self::save_setting( 'tracking_tag', $response['connected_tag_id'] );

			return $response;
		}

		/**
		 * Updates WC integration parameters with Pinterest.
		 *
		 * @since 1.4.0
		 *
		 * @param string $external_business_id External business ID for the integration.
		 * @param array  $data Integration data to update with Pinterest.
		 *
		 * @see Pinterest\API\APIV5::update_commerce_integration
		 * @return array the result of APIV5::update_commerce_integration.
		 * @throws PinterestApiException In case of 404, 409 and 500 errors from Pinterest.
		 */
		public static function update_commerce_integration( string $external_business_id, array $data ): array {
			return Pinterest\API\APIV5::update_commerce_integration( $external_business_id, $data );
		}

		/**
		 * Disconnects WC from Pinterest.
		 *
		 * @since 1.4.0
		 *
		 * @return bool
		 * @throws PinterestApiException In case of 500 unexpected error from Pinterest.
		 */
		public static function delete_commerce_integration(): bool {
			try {
				$external_business_id = self::get_data( 'integration_data' )['external_business_id'];
				Pinterest\API\APIV5::delete_commerce_integration( $external_business_id );
				return true;
			} catch ( PinterestApiException $e ) {
				Logger::log( $e->getMessage(), 'error' );
				return false;
			}
		}

		/**
		 * Used to generate external business id to pass it Pinterest when creating a connection between WC and Pinterest.
		 *
		 * @since 1.4.0
		 *
		 * @return string
		 */
		public static function generate_external_business_id(): string {
			$name = (string) parse_url( esc_url( get_site_url() ), PHP_URL_HOST );
			if ( empty( $name ) ) {
				$name = sanitize_title( get_bloginfo( 'name' ) );
			}
			$id = uniqid( sprintf( 'woo-%s-', $name ), false );

			/**
			 * Filters the shop's external business id.
			 *
			 * This is passed to Pinterest when connecting.
			 * Should be non-empty and without special characters,
			 * otherwise the ID will be obtained from the site's name as fallback.
			 *
			 * @since 1.4.0
			 *
			 * @param string $id the shop's external business id.
			 */
			return (string) apply_filters( 'wc_pinterest_external_business_id', $id );
		}

		/**
		 * Fetches the account_data parameters from Pinterest's API
		 * Saves it to the plugin options and returns it.
		 *
		 * @since 1.0.0
		 *
		 * @return array Account data from Pinterest.
		 *
		 * @throws Exception PHP Exception.
		 */
		public static function update_account_data() {
			try {
				$integration_data = self::get_data( 'integration_data' );
				$account_data     = Pinterest\API\APIV5::get_account_info();

				$data = array(
					'username'         => $account_data['username'] ?? '',
					'full_name'        => '',
					'id'               => $integration_data['id'] ?? '',
					'image_medium_url' => $account_data['profile_image'] ?? '',
					// Partner is a user who is a business account not a pinner ('BUSINESS', 'PINNER' account types).
					'is_partner'       => 'BUSINESS' === ( $account_data['account_type'] ?? '' ),
				);

				$verified_websites = array_reduce(
					Pinterest\API\APIV5::get_user_websites()['items'] ?? array(),
					function ( $carry, $item ) {
						if ( 'verified' === $item['status'] ) {
							$carry[] = $item['website'];
						}
						return $carry;
					},
					array()
				);

				$data += array(
					// Array of verified website domain names.
					'verified_user_websites'  => $verified_websites,
					// Indicates if any of the verified websites is verified true or false.
					'is_any_website_verified' => 0 < count( $verified_websites ),
				);

					/*
					 * For now we assume that the billing is not setup and credits are not redeemed.
					 * We will be able to check that only when the advertiser will be connected.
					 * The billing is tied to advertiser.
					 */
					$data['is_billing_setup']     = false;
					$data['coupon_redeem_info']   = array( 'redeem_status' => false );
					$data['currency_credit_info'] = AdsCreditCurrency::get_currency_credits();

				Pinterest_For_Woocommerce()::save_setting( 'account_data', $data );
				return $data;
			} catch ( Throwable $th ) {
				self::disconnect();
				throw new Exception( esc_html__( 'There was an error getting the account data.', 'pinterest-for-woocommerce' ) );
			}
		}

		/**
		 * Updates linked businesses.
		 *
		 * @since 1.4.0
		 *
		 * @return void
		 */
		public static function update_linked_businesses() {
			self::get_linked_businesses( true );
		}

		/**
		 * Cleanup after the token update.
		 *
		 * @since 1.4.0
		 *
		 * @return void
		 */
		public static function post_update_cleanup() {
			TokenExchangeFailure::delete_failure_note();

			// Update completed successfully.
			Pinterest_For_Woocommerce()::set_api_version( 'v5' );
		}

		/**
		 *
		 * @since 1.2.5
		 *
		 * @return void
		 */
		public static function maybe_check_billing_setup() {
			$account_data          = Pinterest_For_Woocommerce()::get_setting( 'account_data' );
			$has_billing_setup_old = is_array( $account_data ) && ( $account_data['is_billing_setup'] ?? false );
			if ( Billing::should_check_billing_setup_often() ) {
				$has_billing_setup_new = Billing::update_billing_information();
				// Detect change in billing setup to true and try to redeem.
				if ( $has_billing_setup_new && ! $has_billing_setup_old ) {
					AdCredits::handle_redeem_credit();
				}
			}
		}

		/**
		 * Get billing setup information from the account data option.
		 *
		 * @since 1.2.5
		 *
		 * @return bool
		 */
		public static function get_billing_setup_info_from_account_data() {
			$account_data = self::get_setting( 'account_data' );

			return (bool) $account_data['is_billing_setup'];
		}

		/**
		 * Add redeem credits information to the account data option.
		 * Using this function makes sense only when we have a connected advertiser and the billing data is set up.
		 *
		 * @since 1.2.5
		 *
		 * @return void
		 */
		public static function add_redeem_credits_info_to_account_data() {
			$account_data = self::get_setting( 'account_data' );
			$offer_code   = AdCreditsCoupons::get_coupon_for_merchant();

			// Redeem the coupon.
			$error_code    = false;
			$error_message = '';
			$redeem_status = AdCredits::redeem_credits( $offer_code, $error_code, $error_message );

			$redeem_information = array(
				'redeem_status' => $redeem_status,
				'offer_code'    => $offer_code,
				'advertiser_id' => Pinterest_For_Woocommerce()::get_setting( 'tracking_advertiser' ),
				'username'      => $account_data['username'],
				'id'            => $account_data['id'],
				'error_id'      => $error_code,
				'error_message' => $error_message,
			);

			/*
			 * Track the redeemed offer code.
			 */
			self::record_event(
				'pfw_ads_redeem_credits',
				array(
					'redeem_status' => $redeem_information['redeem_status'],
					'offer_code'    => $redeem_information['offer_code'],
					'error_id'      => $redeem_information['error_id'],
				)
			);

			$account_data['coupon_redeem_info'] = $redeem_information;

			self::save_setting( 'account_data', $account_data );
		}

		/**
		 * Add currency_credit_info information to the account data option.
		 *
		 * @since 1.3.9
		 *
		 * @return void
		 */
		public static function add_currency_credits_info_to_account_data() {
			$account_data = self::get_setting( 'account_data' );
			if ( ! isset( $account_data['currency_credit_info'] ) ) {
				// Handle possible false value.
				if ( ! is_array( $account_data ) ) {
					$account_data = array();
				}
				$account_data['currency_credit_info'] = AdsCreditCurrency::get_currency_credits();
				self::save_setting( 'account_data', $account_data );
			}
		}

		/**
		 * Add available credits information to the account data option.
		 *
		 * @since 1.2.5
		 *
		 * @return void
		 */
		public static function add_available_credits_info_to_account_data() {
			$account_data = self::get_setting( 'account_data' );

			try {
				// Check for available discounts.
				$account_data['available_discounts'] = AdCredits::process_available_discounts();
				self::save_setting( 'account_data', $account_data );
			} catch ( Exception $e ) {
				return;
			}
		}

		/**
		 * Fetches a fresh copy (if needed or explicitly requested), of the authenticated user's linked business accounts.
		 *
		 * @param bool $force_refresh Whether to refresh the data from the API.
		 *
		 * @return array
		 */
		public static function get_linked_businesses( bool $force_refresh = false ): array {
			$linked_businesses = ! $force_refresh ? Pinterest_For_Woocommerce()::get_data( 'linked_businesses' ) : null;
			if ( null === $linked_businesses ) {
				$account_data            = Pinterest_For_Woocommerce()::get_setting( 'account_data' );
				$fetch_linked_businesses = ! empty( $account_data ) && array_key_exists( 'is_partner', $account_data ) && ! $account_data['is_partner'];

				try {
					$fetched_businesses = $fetch_linked_businesses ? Pinterest\API\APIV5::get_linked_businesses() : array();

					if ( ! empty( $fetched_businesses ) && 'success' === $fetched_businesses['status'] ) {
						$linked_businesses = $fetched_businesses['data'];
					}

					$linked_businesses = $linked_businesses ?? array();

					self::save_data( 'linked_businesses', $linked_businesses );
				} catch ( PinterestApiException $e ) {
					Logger::log( $e->getMessage(), 'error' );
					$linked_businesses = array();
				}
			}

			return $linked_businesses;
		}

		/**
		 * Returns the Pinterest AccountID from the database.
		 *
		 * @return string|false
		 */
		public static function get_account_id() {
			$account_data = Pinterest_For_Woocommerce()::get_setting( 'account_data' );
			return $account_data['id'] ?? false;
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
		 * Hook the parse_request action and serve the html
		 *
		 * @param WP $wp Current WordPress environment instance.
		 */
		public function verification_request( $wp ) {
			$verification_data = self::get_data( 'verification_data' );
			if ( ! $verification_data || ! array_key_exists( 'filename', $verification_data ) ) {
				return;
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$request = trim( $wp->request ?? $_SERVER['PHP_SELF'] ?? '', '/' );
			if ( $verification_data['filename'] === $request ) {
				wc_nocache_headers();
				header( 'Content-Type: text/html' );
				?>
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta name="p:domain_verify" content="<?php echo esc_attr( $verification_data['verification_code'] ); ?>"/>
	<title></title>
</head>
<body><?php esc_html_e( 'Pinterest for WooCommerce verification page', 'pinterest-for-woocommerce' ); ?></body>
</html>
				<?php
				exit;
			}
		}

		/**
		 * Checks if setup is completed and all requirements are set.
		 *
		 * @return boolean
		 */
		public static function is_setup_complete() {
			return self::is_business_connected() && self::is_domain_verified();
		}


		/**
		 * Checks if connected by checking if there is integration ID in the data store.
		 *
		 * @return boolean
		 */
		public static function is_connected() {
			$integration = self::get_data( 'integration_data' );
			return ! empty( $integration['id'] ?? '' );
		}


		/**
		 * Checks if connected and on a Business account.
		 *
		 * @return boolean
		 */
		public static function is_business_connected() {
			if ( ! self::is_connected() ) {
				return false;
			}

			$account_data = self::get_setting( 'account_data' );

			return isset( $account_data['is_partner'] ) ? (bool) $account_data['is_partner'] : false;
		}



		/**
		 * Checks whether we have verified our current domain, by checking account_data as
		 * returned by Pinterest.
		 *
		 * @return bool
		 */
		public static function is_domain_verified(): bool {
			$account_data     = self::get_setting( 'account_data' );
			$verified_domains = $account_data['verified_user_websites'] ?? array();
			return in_array( wp_parse_url( get_home_url() )['host'] ?? '', $verified_domains );
		}

		/**
		 * Checks if tracking is configured properly and enabled.
		 *
		 * @return boolean
		 */
		public static function is_tracking_configured() {
			return false !== Pinterest\Tracking\Tag::get_active_tag();
		}

		/**
		 * Returns the Terms object for the currently configured base country.
		 *
		 * @return array
		 */
		public static function get_applicable_tos() {

			$base_country = self::get_base_country( null );

			return $base_country && isset( self::TOS_PER_COUNTRY[ $base_country ] ) ? self::TOS_PER_COUNTRY[ $base_country ] : self::TOS_PER_COUNTRY['*'];
		}

		/**
		 * Helper function to return the country set in WC's settings using wc_get_base_location().
		 *
		 * @param string $default_country Default country code to return if no country is set.
		 *
		 * @return mixed|string|null
		 */
		public static function get_base_country( $default_country = 'US' ) {
			if ( ! function_exists( 'wc_get_base_location' ) ) {
				return null;
			}

			$base_location = wc_get_base_location();

			return ! empty( $base_location['country'] ) ? $base_location['country'] : $default_country;
		}

		/**
		 * Adds the onboarding task to the Tasklists.
		 *
		 * @since 1.2.11
		 */
		public function add_onboarding_task() {
			if ( class_exists( TaskLists::class ) ) { // compatibility-code "< WC 5.9". This is added for backward compatibility.
				TaskLists::add_task(
					'extended',
					new Onboarding(
						TaskLists::get_list( 'extended' )
					)
				);
			}
		}
	}

endif;
