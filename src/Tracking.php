<?php
/**
 * Pinterest For WooCommerce Tracking
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class adding Save Pin support.
 */
class Tracking {


	/**
	 * The var used to hold the JS that is to be printed.
	 *
	 * @var string
	 */
	private static $script = '';

	/**
	 * The var used to hold the events specific JS that is to be printed.
	 *
	 * @var array
	 */
	private static $events = array();


	/**
	 * Initiate class.
	 */
	public static function maybe_init() {

		if ( ! self::tracking_enabled() || wp_doing_cron() ) {
			return;
		}

		// Base tag.
		self::base_tag();

		// WC events.
		if ( function_exists( 'WC' ) ) {

			// Product page visit.
			self::page_visit_event();

			// Product category page visit.
			self::category_visit_event();

			// AddToCart - ajax.
			if ( 'yes' === get_option( 'woocommerce_enable_ajax_add_to_cart' ) ) {
				add_action( 'wp_enqueue_scripts', array( __CLASS__, 'ajax_tracking_snippet' ), 20 );
			}

			// AddToCart - non-ajax.
			add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'hook_add_to_cart_event' ), 10, 4 );

			// Checkout.
			add_action( 'woocommerce_before_thankyou', array( __CLASS__, 'hook_checkout_event' ), 10, 1 );

		}

		// Print to head.
		add_action( 'wp_head', array( __CLASS__, 'print_script' ) );
	}


	/**
	 * Adjust given base tag in order to enable Enhanched match, by adding
	 * The hashed user email's either by checking the logged in user or the currently stored
	 * session data.
	 *
	 * See https://help.pinterest.com/en/business/article/enhanced-match
	 *
	 * @param string $base_tag The tag to be filtered.
	 *
	 * @return string
	 */
	public static function enable_enhanched_match( $base_tag ) {

		$user_email = '';

		if ( is_user_logged_in() ) {

			$user       = wp_get_current_user();
			$user_email = $user->user_email;
		}

		if ( empty( $user_email ) ) {
			$session_customer = function_exists( 'WC' ) ? WC()->session->get( 'customer' ) : false;
			$user_email       = $session_customer ? $session_customer['email'] : false;
		}

		if ( empty( $user_email ) ) {
			return $base_tag;
		}

		// Add Hashed e-mail to the JS part.
		$base_tag = preg_replace( '/(pintrk\(\s*\'load\'\s*\,\s*\')(\d+)(\'\s*\))/m', '$1$2\', { em: \'' . md5( $user_email ) . '\' })', $base_tag );

		// Add Hashed e-mail to the <img> part of the tag.
		$base_tag = preg_replace( '/(<img.+src=\\")(.+)(\\")/m', '$1$2&pd[em]=' . md5( $user_email ) . '$3', $base_tag );

		return $base_tag;
	}


	/**
	 * Use woocommerce_add_to_cart to enqueue our AddToCart event.
	 *
	 * @param string  $cart_item_key The cart item's key.
	 * @param integer $product_id    The product ID.
	 * @param integer $quantity      The quantity.
	 * @param integer $variation_id  The Variation ID.
	 *
	 * @return void
	 */
	public static function hook_add_to_cart_event( $cart_item_key, $product_id, $quantity, $variation_id ) {

		if ( wp_doing_ajax() ) {
			return;
		}

		$object_id = empty( $variation_id ) ? $product_id : $variation_id;
		$product   = wc_get_product( $object_id );

		self::add_event(
			'AddToCart',
			array(
				'product_id'     => $product->get_id(),
				'value'          => $product->get_price(),
				'order_quantity' => $quantity,
				'currency'       => get_woocommerce_currency(),
			)
		);

	}


	/**
	 * Use woocommerce_before_thankyou to enqueue our Checkout event.
	 *
	 * @param integer $order_id The Order's ID.
	 *
	 * @return void
	 */
	public static function hook_checkout_event( $order_id ) {

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$order_items    = array();
		$total_quantity = 0;

		foreach ( $order->get_items() as $order_item ) {

			if ( ! method_exists( $order_item, 'get_product' ) ) {
				continue;
			}

			$product = $order_item->get_product();

			$order_items[] = array(
				'product_id'       => $order_item->get_id(),
				'product_name'     => $order_item->get_name(),
				'product_price'    => $product->get_price(),
				'product_quantity' => $order_item->get_quantity(),
			);

			$total_quantity += $order_item->get_quantity();
		}

		self::add_event(
			'checkout',
			array(
				'value'          => $order->get_total(),
				'order_quantity' => $total_quantity,
				'currency'       => $order->get_currency(),
				'line_items'     => $order_items,
			)
		);

	}


	/**
	 * Attaches a piece of JS to wc-add-to-cart script, which binds to the
	 * added_to_cart event, in order to trigger our AddToCart event
	 * when the item is added via AJAX.
	 *
	 * @return void
	 */
	public static function ajax_tracking_snippet() {

		$tracking = 'jQuery( function( $ ) { ;
				$( document.body ).on( \'added_to_cart\', function ( e, fragments, cart_hash, thisbutton ) {
					pintrk( \'track\', \'AddToCart\', {
						\'product_id\': thisbutton.data( \'product_id\' ),
						\'order_quantity\': thisbutton.data( \'quantity\' ),
					});
				} );
			} )';

		wp_add_inline_script( 'wc-add-to-cart', $tracking );

	}


	/**
	 * Checks and returns if tracking is enabled and we got an active tag.
	 *
	 * @return boolean
	 */
	private static function tracking_enabled() {

		if ( ! Pinterest_For_Woocommerce()::get_setting( 'track_conversions' ) || ! self::get_active_tag() ) {
			return false;
		}

		return true;
	}


	/**
	 * Enqueues the base tag for printing.
	 *
	 * @return void
	 */
	private static function base_tag() {

		$active_tag = self::get_active_tag();

		if ( $active_tag ) {
			self::$script .= $active_tag->code_snippet;
		}
	}


	/**
	 * Enqueues the page visit event code for printing.
	 *
	 * @return void
	 */
	private static function page_visit_event() {

		$data = array();

		if ( is_product() ) {

			$product = wc_get_product();

			$data = array(
				'product_id'    => $product->get_id(),
				'product_price' => $product->get_price(),
			);
		}

		self::add_event( 'pagevisit', $data );
	}


	/**
	 * Enqueues the Category visit event code for printing.
	 *
	 * @return void
	 */
	private static function category_visit_event() {

		$data = array();

		if ( is_product_category() ) {

			$queried_object = get_queried_object();
			$term_id        = $queried_object->term_id;

			$data = array(
				'product_category' => $term_id,
			);

			self::add_event( 'ViewCategory', $data );
		}

	}


	/**
	 * Enqueues or prints the given event, depending on if
	 * we have already run wp_head or not.
	 *
	 * @param string $event The event's type.
	 * @param array  $data  The data to be passed to the JS function.
	 *
	 * @return void
	 */
	private static function add_event( $event, $data = array() ) {

		$action = did_action( 'wp_head' ) ? 'print_event' : 'enqueue_event';
		call_user_func_array( array( __CLASS__, $action ), array( $event, $data ) );

	}


	/**
	 * Enqueues the given event.
	 *
	 * @param string $event The event's type.
	 * @param array  $data  The data to be passed to the JS function.
	 *
	 * @return void
	 */
	private static function enqueue_event( $event, $data = array() ) {
		self::$events[] = self::prepare_event_code( $event, $data );
	}


	/**
	 * Prints the given event.
	 *
	 * @param string $event The event's type.
	 * @param array  $data  The data to be passed to the JS function.
	 *
	 * @return void
	 */
	private static function print_event( $event, $data = array() ) {
		echo '<script>' . self::prepare_event_code( $event, $data ) . '</script>'; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}


	/**
	 * Gets the event's JS code to be enqueued or printed.
	 *
	 * @param string $event The event's type.
	 * @param array  $data  The data to be passed to the JS function.
	 *
	 * @return string
	 */
	private static function prepare_event_code( $event, $data = array() ) {
		$data_string = empty( $data ) ? null : wp_json_encode( $data );

		return sprintf(
			'pintrk( \'track\', \'%s\' %s);',
			$event,
			empty( $data_string ) ? '' : ', ' . $data_string
		);
	}


	/**
	 * Get the actual JS & markup for the base tag as configured in the settings.
	 *
	 * @return object|boolean
	 */
	private static function get_active_tag() {

		$active_tag_id = Pinterest_For_Woocommerce()::get_setting( 'active_tag_id' );

		if ( empty( $active_tag_id ) ) {
			return false;
		}

		$account_tags = (array) Pinterest_For_Woocommerce()::get_setting( 'account_tags' );

		return ! empty( $account_tags ) && isset( $account_tags[ $active_tag_id ] ) ? $account_tags[ $active_tag_id ] : false;
	}


	/**
	 * Prints the enqueued base code and events snippets.
	 * Meant to be used in wp_head.
	 *
	 * @return void
	 */
	public static function print_script() {

		if ( ! empty( self::$script ) ) {

			self::$script = Pinterest_For_Woocommerce()::get_setting( 'enhanced_match_support' ) ? self::enable_enhanched_match( self::$script ) : self::$script;

			echo self::$script; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			if ( ! empty( self::$events ) ) {
				echo '<script>' . implode( PHP_EOL, self::$events ) . '</script>'; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}
}
