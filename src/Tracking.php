<?php
/**
 * Pinterest tracking main class.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version 1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Pinterest\Tracking\Conversions;
use Automattic\WooCommerce\Pinterest\Tracking\Data;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Category;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Checkout;
use Automattic\WooCommerce\Pinterest\Tracking\Data\None;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Product;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Search;
use Automattic\WooCommerce\Pinterest\Tracking\PageVisit;
use Automattic\WooCommerce\Pinterest\Tracking\Tag;
use Automattic\WooCommerce\Pinterest\Tracking\Tracker;
use Automattic\WooCommerce\Pinterest\Utilities\CrawlerDetector;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tracker responsible for hooking into system events.
 */
class Tracking {

	const EVENT_CHECKOUT      = 'Checkout';

	const EVENT_ADD_TO_CART   = 'AddToCart';

	const EVENT_PAGE_VISIT    = 'PageVisit';

	const EVENT_SEARCH        = 'Search';

	const EVENT_VIEW_CATEGORY = 'ViewCategory';

	/**
	 * WooCommerce session key holding recently reported AddToCart signatures.
	 *
	 * @since 1.5.1
	 *
	 * @var string
	 */
	private const ADD_TO_CART_SIGNATURES_SESSION_KEY = 'pinterest_for_woocommerce_add_to_cart_signatures';

	/**
	 * How long, in seconds, a reported AddToCart signature suppresses a repeat.
	 *
	 * The window slides: every suppressed repeat pushes the expiry forward, so a
	 * chain of retries spaced under the window is collapsed into one event.
	 *
	 * @since 1.5.1
	 *
	 * @var int
	 */
	private const ADD_TO_CART_REPEAT_WINDOW = 30;

	/**
	 * AddToCart signatures already reported during the current request.
	 *
	 * @since 1.5.1
	 *
	 * @var array<string, true>
	 */
	private $reported_add_to_cart_signatures = array();

	/**
	 * @var Tracker[] $trackers A list of available trackers.
	 */
	private $trackers = array();

	/**
	 * Attaches all the required tracking events to corresponding WP/WC hooks.
	 *
	 * @since 1.4.0
	 *
	 * @param array $trackers A list of trackers to track events with.
	 */
	public function __construct( array $trackers = array() ) {
		$this->trackers = $trackers;

		// Tracks page visit events.
		add_action( 'wp_footer', array( $this, 'handle_page_visit' ) );

		// Tracks category visit events.
		add_action( 'wp_footer', array( $this, 'handle_view_category' ) );

		// Tracks search events.
		add_action( 'wp_footer', array( $this, 'handle_search' ) );

		// Tracks add to cart events.
		add_action( 'woocommerce_add_to_cart', array( $this, 'handle_add_to_cart' ), 10, 6 );

		// Customer cart changes made outside add-to-cart release the AddToCart repeat guard.
		add_action( 'woocommerce_cart_item_removed', array( $this, 'handle_cart_item_removed' ), 10, 2 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'handle_cart_item_quantity_update' ), 10, 4 );
		add_action( 'woocommerce_cart_emptied', array( $this, 'handle_cart_emptied' ) );

		// Tracks checkout events.
		add_action( 'woocommerce_before_thankyou', array( $this, 'handle_checkout' ), 10, 2 );

		array_walk(
			$this->trackers,
			fn ( $tracker ) => call_user_func( array( $tracker, 'init_hooks' ) )
		);
	}

	/**
	 * Used as a callback for the wp_footer hook.
	 *
	 * @since 1.4.0
	 * @since 1.4.8 Added check for product page.
	 * @since 1.5.1 Prints the CAPI beacon itself when no Tag is active.
	 *
	 * @return void
	 */
	public function handle_page_visit() {
		if ( is_404() ) {
			// Do not track 404 pages.
			return;
		}

		// The PageVisit event ID is generated in the browser so full-page caches
		// cannot reuse a PHP-generated ID across multiple visitors.
		$data    = new None( '' );
		$product = is_product() ? wc_get_product() : false;

		if ( $product instanceof \WC_Product ) {
			$data = new Product(
				'',
				$product->get_id(),
				$product->get_name(),
				wc_get_product_category_list( $product->get_id() ),
				'brand',
				wc_get_price_to_display( $product ),
				get_woocommerce_currency(),
				1
			);
		}

		$this->track_event( static::EVENT_PAGE_VISIT, $data );

		// The Tag tracker prints the beacon together with its PageVisit call, but it
		// is skipped without an active Tag, so print the beacon here for CAPI-only stores.
		if ( ! Tag::get_active_tag() && $this->has_tracker( Conversions::class ) ) {
			PageVisit::print_beacon_script();
		}
	}

	/**
	 * Used as a callback for the wp_footer hook.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public function handle_view_category() {
		if ( ! is_product_category() ) {
			return;
		}
		$queried_object = get_queried_object();
		$data           = new Category(
			uniqid( 'category' ),
			$queried_object->term_id,
			$queried_object->name
		);
		$this->track_event( static::EVENT_VIEW_CATEGORY, $data );
	}

	/**
	 * Used as a callback for the woocommerce_add_to_cart hook.
	 *
	 * @since 1.4.0
	 *
	 * @param string $cart_item_key - WooCommerce cart item key.
	 * @param string $product_id           - WooCommerce product id.
	 * @param string $quantity             - Number of products.
	 * @param string $variation_id         - Product variation id if any.
	 *
	 * @return void
	 */
	public function handle_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id ) {
		$object_id = empty( $variation_id ) ? $product_id : $variation_id;
		$product   = wc_get_product( $object_id );
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		if ( ! $this->should_report_add_to_cart( $cart_item_key ) ) {
			return;
		}

		$data = new Product(
			uniqid( 'cart' ),
			$product->get_id(),
			$product->get_name(),
			wc_get_product_category_list( $product->get_id() ),
			'brand',
			wc_get_price_to_display( $product ),
			get_woocommerce_currency(),
			$quantity
		);
		$this->track_event( static::EVENT_ADD_TO_CART, $data );
	}

	/**
	 * Used as a callback for the woocommerce_cart_item_removed hook.
	 *
	 * A product removed and added again inside the repeat window is a new
	 * customer action, so the removal releases the guard for that item.
	 *
	 * @since 1.5.1
	 *
	 * @param string   $cart_item_key WooCommerce cart item key.
	 * @param \WC_Cart $cart          Cart the item was removed from.
	 *
	 * @return void
	 */
	public function handle_cart_item_removed( $cart_item_key, $cart ) {
		if ( $this->is_customer_cart( $cart ) ) {
			$this->forget_add_to_cart_signatures( $cart_item_key );
		}
	}

	/**
	 * Used as a callback for the woocommerce_after_cart_item_quantity_update hook.
	 *
	 * A quantity decrease means a later add that lands on the old quantity is a
	 * new customer action, not a repeat. Increases are left alone: add_to_cart()
	 * raises the quantity through set_quantity() before firing its own hook.
	 *
	 * @since 1.5.1
	 *
	 * @param string    $cart_item_key WooCommerce cart item key.
	 * @param int|float $quantity      New quantity.
	 * @param int|float $old_quantity  Previous quantity.
	 * @param \WC_Cart  $cart          Cart the item belongs to.
	 *
	 * @return void
	 */
	public function handle_cart_item_quantity_update( $cart_item_key, $quantity, $old_quantity, $cart ) {
		if ( $quantity < $old_quantity && $this->is_customer_cart( $cart ) ) {
			$this->forget_add_to_cart_signatures( $cart_item_key );
		}
	}

	/**
	 * Used as a callback for the woocommerce_cart_emptied hook.
	 *
	 * The hook does not pass the cart, so the customer's cart is inspected
	 * instead: empty_cart() clears its contents before firing, so a customer cart
	 * that still holds items means some other cart was emptied.
	 *
	 * @since 1.5.1
	 *
	 * @return void
	 */
	public function handle_cart_emptied() {
		if ( function_exists( 'WC' ) && isset( WC()->cart ) && WC()->cart->is_empty() ) {
			$this->forget_add_to_cart_signatures();
		}
	}

	/**
	 * Returns true when the cart passed by a hook is the customer's cart.
	 *
	 * Cart hooks are global, so they also fire for throwaway carts that express
	 * checkout buttons build and dispose of. Only the customer's cart may
	 * release the guard, otherwise a simulator cleaning up after itself would
	 * reopen a claim on the same product in the customer's cart.
	 *
	 * @since 1.5.1
	 *
	 * @param mixed $cart Cart passed by the hook.
	 *
	 * @return bool
	 */
	private function is_customer_cart( $cart ) {
		return function_exists( 'WC' ) && isset( WC()->cart ) && WC()->cart === $cart;
	}

	/**
	 * Drops reported AddToCart signatures for a cart item, or for every item.
	 *
	 * @since 1.5.1
	 *
	 * @param string $cart_item_key WooCommerce cart item key, or empty for all.
	 *
	 * @return void
	 */
	private function forget_add_to_cart_signatures( $cart_item_key = '' ) {
		$keep = function ( $signature ) use ( $cart_item_key ) {
			return '' !== $cart_item_key && 0 !== strpos( $signature, $cart_item_key . ':' );
		};

		$this->reported_add_to_cart_signatures = array_filter( $this->reported_add_to_cart_signatures, $keep, ARRAY_FILTER_USE_KEY );

		$session = function_exists( 'WC' ) && isset( WC()->session ) ? WC()->session : false;
		if ( ! $session ) {
			return;
		}

		$signatures = $session->get( self::ADD_TO_CART_SIGNATURES_SESSION_KEY );
		if ( ! is_array( $signatures ) || empty( $signatures ) ) {
			return;
		}

		$session->set( self::ADD_TO_CART_SIGNATURES_SESSION_KEY, array_filter( $signatures, $keep, ARRAY_FILTER_USE_KEY ) );
	}

	/**
	 * Decides whether an add-to-cart hook fire is a customer action worth reporting.
	 *
	 * `woocommerce_add_to_cart` is a global action, so it also fires for carts
	 * that are not the customer's. Express checkout buttons add products to a
	 * throwaway WC_Cart to price them, which runs add_to_cart() for real while
	 * WC()->cart is left untouched. Retried requests fire it again for a cart
	 * that did not change. Neither is a customer adding something to their cart.
	 *
	 * @since 1.5.1
	 *
	 * @param string $cart_item_key WooCommerce cart item key.
	 *
	 * @return bool
	 */
	private function should_report_add_to_cart( $cart_item_key ) {
		if ( $this->is_simulated_add_to_cart() ) {
			return false;
		}

		$signature = $this->get_customer_cart_signature( $cart_item_key );

		return '' !== $signature && $this->claim_add_to_cart_signature( $signature );
	}

	/**
	 * Builds a signature describing the customer cart state this fire reports.
	 *
	 * Reads the customer's cart rather than trusting the hook arguments, so an
	 * add that landed in some other cart yields no signature. The signature pairs
	 * the cart item with the quantity it holds once the item has been added, so
	 * two fires that leave the cart in the same state share a signature however
	 * many times WooCommerce ran add_to_cart().
	 *
	 * @since 1.5.1
	 *
	 * @param string $cart_item_key WooCommerce cart item key.
	 *
	 * @return string Signature, empty when the item is not in the customer's cart.
	 */
	private function get_customer_cart_signature( $cart_item_key ) {
		if ( ! function_exists( 'WC' ) || ! isset( WC()->cart ) ) {
			return '';
		}

		$cart_item = WC()->cart->get_cart_item( $cart_item_key );
		if ( empty( $cart_item ) ) {
			return '';
		}

		return $cart_item_key . ':' . $cart_item['quantity'];
	}

	/**
	 * Returns true while another extension is pricing a simulated cart.
	 *
	 * WooCommerce PayPal Payments raises this flag around the isolated cart its
	 * `ppc-simulate-cart` endpoint builds. The cart lookup alone cannot catch a
	 * simulation of a product the customer already holds, because it produces
	 * the cart item key that is in their cart.
	 *
	 * @since 1.5.1
	 *
	 * @return bool
	 */
	private function is_simulated_add_to_cart() {
		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Filter owned by WooCommerce PayPal Payments, read only.
		return (bool) apply_filters( 'woocommerce_paypal_payments_is_simulating_cart', false );
	}

	/**
	 * Claims a signature for reporting, or refuses it as a repeat.
	 *
	 * Retried or replayed add-to-cart requests leave the cart unchanged, so they
	 * produce a signature that was claimed moments earlier. Claiming is recorded
	 * per request and in the WooCommerce session, which is what carries the guard
	 * across the separate requests a retry produces.
	 *
	 * @since 1.5.1
	 *
	 * @param string $signature Signature from get_customer_cart_signature().
	 *
	 * @return bool True when the caller should report the event.
	 */
	private function claim_add_to_cart_signature( $signature ) {
		if ( isset( $this->reported_add_to_cart_signatures[ $signature ] ) ) {
			return false;
		}
		$this->reported_add_to_cart_signatures[ $signature ] = true;

		$session = function_exists( 'WC' ) && isset( WC()->session ) ? WC()->session : false;
		if ( ! $session ) {
			return true;
		}

		$now        = time();
		$signatures = $session->get( self::ADD_TO_CART_SIGNATURES_SESSION_KEY );
		$signatures = is_array( $signatures ) ? $signatures : array();

		$signatures = array_filter(
			$signatures,
			function ( $claimed_at ) use ( $now ) {
				return is_int( $claimed_at ) && $now - $claimed_at < self::ADD_TO_CART_REPEAT_WINDOW;
			}
		);

		$is_repeat                = isset( $signatures[ $signature ] );
		$signatures[ $signature ] = $now;

		$session->set( self::ADD_TO_CART_SIGNATURES_SESSION_KEY, $signatures );

		// Persist the claim now rather than at shutdown. The Conversions request
		// runs before shutdown and can block for seconds, which is long enough for
		// a retried add-to-cart request to read a session that has not been written.
		if ( method_exists( $session, 'save_data' ) ) {
			$session->save_data();
		}

		return ! $is_repeat;
	}

	/**
	 * Used as a callback for the woocommerce_before_thankyou hook.
	 *
	 * @since 1.4.0
	 *
	 * @param string $order_id WooCommerce order id.
	 *
	 * @return void
	 */
	public function handle_checkout( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$items          = array();
		$total_quantity = 0;
		$checkout_value = 0;
		foreach ( $order->get_items() as $order_item ) {
			if ( ! method_exists( $order_item, 'get_product' ) ) {
				continue;
			}

			$product = $order_item->get_product();

			$items[] = new Product(
				uniqid( 'product' ),
				$product->get_id(),
				$order_item->get_name(),
				wc_get_product_category_list( $product->get_id() ),
				'brand',
				$order->get_item_total( $order_item, false, true ),
				get_woocommerce_currency(),
				$order_item->get_quantity()
			);

			$total_quantity += $order_item->get_quantity();
			$checkout_value += (float) $order_item->get_total();
		}

		// Deterministic event id so Pinterest can deduplicate Tag/CAPI Checkout events when
		// the thank-you page is re-rendered (woocommerce_before_thankyou fires on every render).
		$data = new Checkout(
			'checkout_' . $order->get_id(),
			(string) $order->get_id(),
			wc_format_decimal( $checkout_value, wc_get_price_decimals() ),
			$total_quantity,
			$order->get_currency(),
			$items
		);
		$this->track_event( static::EVENT_CHECKOUT, $data );
	}

	/**
	 * Search event handler.
	 *
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public function handle_search() {
		if ( ! is_search() ) {
			return;
		}

		$data = new Search(
			uniqid( 'pinterest-for-woocommerce-tag-and-conversions-event-id' ),
			get_search_query()
		);
		$this->track_event( static::EVENT_SEARCH, $data );
	}

	/**
	 * Method which iterates over all the attached trackers and delegates the event to them.
	 *
	 * Server-side trackers (Conversions API) are skipped for crawler/bot requests.
	 * Browser-side rendering (Tag JS) is intentionally NOT skipped, so full-page
	 * caches that omit `Vary: User-Agent` do not serve bot-rendered HTML missing
	 * Tag JS to real users on a subsequent cache hit. See CrawlerDetector.
	 *
	 * @since 1.4.0
	 *
	 * @param string $event_name Tracking event name.
	 * @param Data   $data       Event Data object.
	 *
	 * @return void
	 */
	public function track_event( string $event_name, Data $data ) {
		$is_crawler = CrawlerDetector::is_crawler_request();

		foreach ( $this->get_trackers() as $tracker ) {
			// Skip Pinterest tag tracking if tag is not active.
			if ( $tracker instanceof Tag && ! Tag::get_active_tag() ) {
				continue;
			}

			// Skip server-side CAPI dispatch for crawler requests so bot
			// traffic does not inflate CAPI counts vs Tag counts.
			if ( $is_crawler && $tracker instanceof Conversions ) {
				continue;
			}

			// PageVisit CAPI events are dispatched by the browser beacon so they
			// also run when the page HTML is served from a full-page cache.
			if ( static::EVENT_PAGE_VISIT === $event_name && $tracker instanceof Conversions ) {
				continue;
			}

			try {
				$tracker->track_event( $event_name, $data );
			} catch ( Throwable $e ) {
				/* translators: %1$s - event name, %2$s - tracker class name, %3$s - error message */
				$message = sprintf(
					'Error while tracking event %1$s with tracker %2$s. Error: %3$s',
					$event_name,
					get_class( $tracker ),
					$e->getMessage()
				);
				Logger::log( $message, 'error' );
			}
		}
	}

	/**
	 * Returns an array of registered trackers.
	 *
	 * @since 1.4.0
	 *
	 * @return Tracker[]
	 */
	public function get_trackers() {
		return $this->trackers;
	}

	/**
	 * Checks whether a tracker of the given class is registered.
	 *
	 * @since 1.5.1
	 *
	 * @param string $tracker_class Tracker class name. e.g. Tag::class, Conversions::class.
	 *
	 * @return bool
	 */
	private function has_tracker( string $tracker_class ) {
		foreach ( $this->trackers as $tracker ) {
			if ( $tracker instanceof $tracker_class ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Adds a tracker to the array of trackers.
	 *
	 * @since 1.4.0
	 *
	 * @param Tracker $tracker - One of objects implementing Tracker interface.
	 *
	 * @return void
	 */
	public function add_tracker( Tracker $tracker ) {
		$tracker->init_hooks();
		$this->trackers[ get_class( $tracker ) ] = $tracker;
	}

	/**
	 * Removes a tracker.
	 *
	 * @since 1.4.0
	 *
	 * @param string $tracker Tracker class name to be removed. e.g. Tag::class, Conversions::class.
	 *
	 * @return void
	 */
	public function remove_tracker( string $tracker ) {
		$this->trackers = array_filter(
			$this->trackers,
			function ( $item ) use ( $tracker ) {
				if ( get_class( $item ) === $tracker ) {
					$item->disable_hooks();
					return false;
				}
				return true;
			}
		);
	}
}
