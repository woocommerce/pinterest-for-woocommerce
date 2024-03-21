<?php
/**
 * Pinterest for WooCommerce Tracking. Pinterest Tag.
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\Tracking;

use Automattic\WooCommerce\Pinterest\Tracking;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Category;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Checkout;
use Automattic\WooCommerce\Pinterest\Tracking\Data\None;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Product;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Search;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class adds PinterestTag tracker support.
 */
class Tag extends Tracker {

	private const TAG_ID_SLUG       = '%%TAG_ID%%';
	private const HASHED_EMAIL_SLUG = '%%HASHED_EMAIL%%';

	/**
	 * The base tracking snippet.
	 * Documentation: https://help.pinterest.com/en/business/article/install-the-pinterest-tag
	 *
	 * @var string
	 */
	private static $base_tag = "<!-- Pinterest Pixel Base Code -->\n<script type=\"text/javascript\">\n  !function(e){if(!window.pintrk){window.pintrk=function(){window.pintrk.queue.push(Array.prototype.slice.call(arguments))};var n=window.pintrk;n.queue=[],n.version=\"3.0\";var t=document.createElement(\"script\");t.async=!0,t.src=e;var r=document.getElementsByTagName(\"script\")[0];r.parentNode.insertBefore(t,r)}}(\"https://s.pinimg.com/ct/core.js\");\n\n  pintrk('load', '" . self::TAG_ID_SLUG . "', { np: \"woocommerce\" } );\n  pintrk('page');\n</script>\n<!-- End Pinterest Pixel Base Code -->\n";

	/**
	 * The base tracking snippet with Enchanced match support.
	 * Documentation: https://help.pinterest.com/en/business/article/enhanced-match
	 *
	 * @var string
	 */
	private static $base_tag_em = "<!-- Pinterest Pixel Base Code -->\n<script type=\"text/javascript\">\n  !function(e){if(!window.pintrk){window.pintrk=function(){window.pintrk.queue.push(Array.prototype.slice.call(arguments))};var n=window.pintrk;n.queue=[],n.version=\"3.0\";var t=document.createElement(\"script\");t.async=!0,t.src=e;var r=document.getElementsByTagName(\"script\")[0];r.parentNode.insertBefore(t,r)}}(\"https://s.pinimg.com/ct/core.js\");\n\n pintrk('load', '" . self::TAG_ID_SLUG . "', { em: '" . self::HASHED_EMAIL_SLUG . "', np: \"woocommerce\" });\n  pintrk('page');\n</script>\n<!-- End Pinterest Pixel Base Code -->\n";

	/**
	 * The noscript base tracking snippet.
	 * Documentation: https://help.pinterest.com/en/business/article/install-the-pinterest-tag
	 *
	 * @var string
	 */
	private static $noscript_base_tag = '<!-- Pinterest Pixel Base Code --><noscript><img height="1" width="1" style="display:none;" alt="" src="https://ct.pinterest.com/v3/?tid=' . self::TAG_ID_SLUG . '&noscript=1" /></noscript><!-- End Pinterest Pixel Base Code -->';

	/**
	 * A list of events that are to be printed out.
	 *
	 * @var array
	 */
	private static $events = array();

	/**
	 * A list of events that are to be stored.
	 *
	 * @var array
	 */
	private static $deferred_events = array();

	/**
	 * Initialises hooks a tracker need to operate.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'wp_footer', array( $this, 'print_script' ) );
		add_action( 'wp_footer', array( $this, 'print_noscript' ) );
		add_action( 'shutdown', array( $this, 'save_deferred_events' ) );
	}

	/**
	 * Disables hooks a tracker could set.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function disable_hooks() {
		remove_action( 'wp_footer', array( $this, 'print_script' ) );
		remove_action( 'wp_footer', array( $this, 'print_noscript' ) );
		remove_action( 'shutdown', array( $this, 'save_deferred_events' ) );
	}

	/**
	 * Renders Pinterest Tag script part.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function print_script() {
		$active_tag = Pinterest_For_Woocommerce()::get_setting( 'tracking_tag' );
		$email      = Pinterest_For_Woocommerce()::get_setting( 'enhanced_match_support' )
			? static::maybe_get_hashed_customer_email()
			: '';

		$script = ! empty( $email ) ? self::$base_tag_em : self::$base_tag;
		$script = str_replace(
			array( self::TAG_ID_SLUG, self::HASHED_EMAIL_SLUG ),
			array( sanitize_key( $active_tag ), $email ),
			$script
		);
		//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $script;

		$events = array_merge(
			static::$events,
			static::load_deferred_events()
		);
		if ( ! empty( $events ) ) {
			//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<script>' . implode( PHP_EOL, $events ) . '</script>';
		}

		//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<script id="pinterest-tag-placeholder"></script>';
	}

	/**
	 * Renders Pinterest Tag <noscript/> part.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function print_noscript() {
		$active_tag = Pinterest_For_Woocommerce()::get_setting( 'tracking_tag' );
		if ( ! $active_tag ) {
			return;
		}
		$noscript = str_replace( self::TAG_ID_SLUG, sanitize_key( $active_tag ), self::$noscript_base_tag );
		echo $noscript; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped --- Printing hardcoded JS tracking code.
	}

	/**
	 * Generates Pinterest Tag event call.
	 *
	 * @since x.x.x
	 *
	 * @param string $event_name Event name. e.g. Checkout, AddToCart, etc.
	 * @param array  $data       Corresponding event data object.
	 *
	 * @return string A generated event call.
	 */
	private static function get_event_code( string $event_name, array $data ) {
		$data_string = empty( $data ) ? null : wp_json_encode( $data );
		return sprintf(
			'pintrk( \'track\', \'%s\' %s);',
			$event_name,
			empty( $data_string ) ? '' : ', ' . $data_string
		);
	}

	/**
	 * Loads deferred event from the storage.
	 *
	 * @since x.x.x
	 *
	 * @return array
	 */
	public static function load_deferred_events() {
		$transient_key = static::get_deferred_events_transient_key();
		if ( ! $transient_key ) {
			return array();
		}

		$async_events = get_transient( $transient_key );
		if ( ! $async_events ) {
			return array();
		}

		delete_transient( $transient_key );
		return $async_events;
	}

	/**
	 * Adds event into the list of events to be delayed.
	 *
	 * @since x.x.x
	 *
	 * @param string $event_name Event name. e.g. Checkout, AddToCart, etc.
	 * @param array  $data       Corresponding event data object.
	 *
	 * @return true
	 */
	public static function add_deferred_event( string $event_name, array $data ) {
		static::$deferred_events[] = static::get_event_code( $event_name, $data );
		return true;
	}

	/**
	 * Adds event into the list of events to be rendered.
	 *
	 * @since x.x.x
	 *
	 * @param string $event_name Event name. e.g. Checkout, AddToCart, etc.
	 * @param array  $data       Corresponding event data object.
	 *
	 * @return true
	 */
	public static function add_event( string $event_name, array $data ) {
		static::$events[] = static::get_event_code( $event_name, $data );
		return true;
	}

	/**
	 * Saves events to be rendered the next page load.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public static function save_deferred_events() {
		$transient_key = static::get_deferred_events_transient_key();
		if ( ! $transient_key ) {
			return;
		}

		$existing_events         = static::load_deferred_events();
		static::$deferred_events = array_merge( $existing_events, static::$deferred_events );

		if ( ! empty( static::$deferred_events ) ) {
			set_transient( $transient_key, static::$deferred_events, DAY_IN_SECONDS );
		}
	}

	/**
	 * Tracks event.
	 *
	 * @since x.x.x
	 *
	 * @param string $event_name Event name. e.g. Checkout, AddToCart, etc.
	 * @param Data   $data       Corresponding event data object.
	 *
	 * @return true
	 */
	public function track_event( string $event_name, Data $data ) {
		$data = $this->prepare_request_data( $event_name, $data );
		if ( wp_doing_ajax() ) {
			return static::maybe_add_fragment( $event_name, $data );
		}

		/**
		 * If the cart redirect is enabled, force add the event to the deferred events list
		 * because if redirect is enabled, the cart page will be reloaded and the event will get lost.
		 */
		$is_redirect = 'yes' === get_option( 'woocommerce_cart_redirect_after_add' );
		/*
		 * This check is made for Add to Cart when using Blocks. WP does not detect
		 * the AJAX request in this case, so we must add the event to the deferred
		 * list.
		 */
		$is_ajax = 'yes' === get_option( 'woocommerce_enable_ajax_add_to_cart' );
		$is_add_to_cart = Tracking::EVENT_ADD_TO_CART === $event_name;
		if ( ( $is_redirect || $is_ajax ) && $is_add_to_cart ) {
			return static::add_deferred_event( $event_name, $data );
		}

		return static::add_event( $event_name, $data );
	}

	/**
	 * Prepares event data for the request.
	 *
	 * @since x.x.x
	 *
	 * @param string $event_name Event name. e.g. Checkout, AddToCart, etc.
	 * @param Data   $data       Corresponding event data object.
	 *
	 * @return array Event data.
	 */
	public function prepare_request_data( string $event_name, Data $data ) {
		$event_name = static::EVENT_MAP[ $event_name ] ?? '';
		$method     = "get_{$event_name}_data";
		if ( method_exists( $this, $method ) ) {
			$prepared_data = call_user_func( array( $this, $method ), $data );
		} else {
			$prepared_data = array(
				'event_id' => $data->get_event_id(),
			);
		}
		return $prepared_data;
	}

	/**
	 * Prepares data for search event.
	 *
	 * @see Tag::prepare_request_data()
	 * @since x.x.x
	 *
	 * @param Search $data Search tracking data.
	 *
	 * @return array Prepared request data.
	 */
	private function get_search_data( Search $data ) {
		return array(
			'event_id'     => $data->get_event_id(),
			'search_query' => $data->get_search_query(),
		);
	}

	/**
	 * Prepares data for page visit event.
	 *
	 * @see Tag::prepare_request_data()
	 * @since x.x.x
	 *
	 * @param Product|None $data Product tracking data.
	 *
	 * @return array Prepared request data.
	 */
	private function get_page_visit_data( Data $data ) {
		if ( $data instanceof None ) {
			return array(
				'event_id' => $data->get_event_id(),
			);
		}

		return array(
			'event_id'      => $data->get_event_id(),
			'product_id'    => $data->get_id(),
			'product_name'  => $data->get_name(),
			'product_price' => $data->get_price(),
			'currency'      => $data->get_currency(),
		);
	}

	/**
	 * Prepares data for view category event.
	 *
	 * @see Tag::prepare_request_data()
	 * @since x.x.x
	 *
	 * @param Category $data Category tracking data.
	 *
	 * @return array Prepared request data.
	 */
	private function get_view_category_data( Category $data ) {
		return array(
			'event_id'         => $data->get_event_id(),
			'product_category' => $data->get_id(),
			'category_name'    => $data->get_name(),
		);
	}

	/**
	 * Prepares data for checkout event.
	 *
	 * @see Tag::prepare_request_data()
	 * @since x.x.x
	 *
	 * @param Checkout $data Checkout tracking data.
	 *
	 * @return array Prepared request data.
	 */
	private function get_checkout_data( Checkout $data ) {
		return array(
			'event_id'       => $data->get_event_id(),
			'order_id'       => $data->get_order_id(),
			'value'          => $data->get_price(),
			'order_quantity' => $data->get_quantity(),
			'currency'       => $data->get_currency(),
			'line_items'     => array_map(
				function ( $item ) {
					return array(
						'product_id'       => $item->get_id(),
						'product_name'     => $item->get_name(),
						'product_price'    => $item->get_price(),
						'product_quantity' => $item->get_quantity(),
						'product_category' => $item->get_category(),
					);
				},
				$data->get_items()
			),
		);
	}

	/**
	 * Prepares data for add to cart event.
	 *
	 * @see Tag::prepare_request_data()
	 * @since x.x.x
	 *
	 * @param Product $data Product tracking data.
	 *
	 * @return array Prepared request data.
	 */
	private function get_add_to_cart_data( Product $data ) {
		return array(
			'event_id'       => $data->get_event_id(),
			'product_id'     => $data->get_id(),
			'product_name'   => $data->get_name(),
			'value'          => $data->get_price() * $data->get_quantity(),
			'order_quantity' => $data->get_quantity(),
			'currency'       => $data->get_currency(),
		);
	}

	/**
	 * @return mixed
	 */
	public static function get_active_tag() {
		return Pinterest_For_Woocommerce()::get_setting( 'tracking_tag' );
	}

	/**
	 * Get the formatted warning message for the potential conflicting tags.
	 *
	 * @since x.x.x
	 *
	 * @return string The warning message.
	 */
	public static function get_third_party_tags_warning_message() {

		$third_party_tags = self::get_third_party_installed_tags();

		if ( empty( $third_party_tags ) ) {
			return '';
		}

		return sprintf(
		/* Translators: 1: Conflicting plugins, 2: Plugins Admin page opening tag, 3: Pinterest settings opening tag, 4: Closing anchor tag */
			esc_html__( 'The following installed plugin(s) can potentially cause problems with tracking: %1$s. %2$sRemove conflicting plugins%4$s or %3$smanage tracking settings%4$s.', 'pinterest-for-woocommerce' ),
			implode( ', ', $third_party_tags ),
			sprintf( '<a href="%s" target="_blank">', esc_url( admin_url( 'plugins.php' ) ) ),
			sprintf( '<a href="%s" target="_blank">', esc_url( wc_admin_url( '&path=/pinterest/settings' ) ) ),
			'</a>',
		);
	}

	/**
	 * Detect if there are other tags installed on the site.
	 *
	 * @since x.x.x
	 *
	 * @return array The list of installed tags.
	 */
	public static function get_third_party_installed_tags() {

		$third_party_tags = array();

		if ( defined( 'GTM4WP_VERSION' ) ) {
			$third_party_tags['gtm'] = 'Google Tag Manager';
		}

		if ( defined( 'PYS_PINTEREST_VERSION' ) ) {
			$third_party_tags['pys'] = 'Pixel Your Site - Pinterest Addon';
		}

		if ( class_exists( PinterestPlugin::class ) ) {
			$third_party_tags['softblues'] = 'Pinterest for WooCommerce by Softblues';
		}

		return $third_party_tags;
	}

	/**
	 * Returns the hashed email of the current user if any.
	 *
	 * @return string
	 */
	private static function maybe_get_hashed_customer_email() {
		$user_email = '';
		if ( is_user_logged_in() ) {
			$user       = wp_get_current_user();
			$user_email = $user->user_email;
		}
		if ( empty( $user_email ) ) {
			$session_customer = function_exists( 'WC' ) && isset( WC()->session ) ? WC()->session->get( 'customer' ) : false;
			$user_email       = $session_customer ? $session_customer['email'] : '';
		}
		return $user_email ? md5( $user_email ) : '';
	}

	/**
	 * Returns the transient key for deferred events based on user session.
	 *
	 * @return string
	 */
	private static function get_deferred_events_transient_key() {
		if ( is_object( WC()->session ) ) {
			return 'pinterest_for_woocommerce_async_events_' . md5( WC()->session->get_customer_id() );
		}
		return '';
	}

	/**
	 * Adds a fragment to trigger Pinterest Tag event on add to cart event.
	 *
	 * @since x.x.x
	 *
	 * @param string $event_name Event name.
	 * @param array  $data       Event data.
	 * @return bool
	 */
	private static function maybe_add_fragment( string $event_name, array $data ) {
		if ( Tracking::EVENT_ADD_TO_CART === $event_name ) {
			$event = static::get_event_code( $event_name, $data );
			add_filter(
				'woocommerce_add_to_cart_fragments',
				function ( $fragments ) use ( $event ) {
					$fragments['script#pinterest-tag-placeholder'] = <<<JS
<script id="pinterest-tag-placeholder">
	{$event}
</script>
JS;
					return $fragments;
				}
			);
		}
		return true;
	}
}
