<?php
/**
 * Pinterest tracking main class.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version 1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Pinterest\Tracking\Data;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Category;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Checkout;
use Automattic\WooCommerce\Pinterest\Tracking\Data\None;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Product;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Search;
use Automattic\WooCommerce\Pinterest\Tracking\Tag;
use Automattic\WooCommerce\Pinterest\Tracking\Tracker;
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
	 * @var array $trackers A list of available trackers.
	 */
	private $trackers = array();

	/**
	 * Attaches all the required tracking events to corresponding WP/WC hooks.
	 *
	 * @since x.x.x
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

		// Tracks checkout events.
		add_action( 'woocommerce_before_thankyou', array( $this, 'handle_checkout' ), 10, 2 );
	}

	/**
	 * Used as a callback for the wp_footer hook.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function handle_page_visit() {
		$data = new None( uniqid( 'page' ) );
		if ( is_product() ) {
			$product = wc_get_product();
			$data    = new Product(
				uniqid( 'page' ),
				$product->get_id(),
				$product->get_name(),
				wc_get_product_category_list( $product->get_id() ),
				'brand',
				$product->get_price(),
				get_woocommerce_currency(),
				1
			);
		}
		$this->track_event( static::EVENT_PAGE_VISIT, $data );
	}

	/**
	 * Used as a callback for the wp_footer hook.
	 *
	 * @since x.x.x
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
	 * @since x.x.x
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
		$data      = new Product(
			uniqid( 'cart' ),
			$product->get_id(),
			$product->get_name(),
			wc_get_product_category_list( $product->get_id() ),
			'brand',
			$product->get_price(),
			get_woocommerce_currency(),
			$quantity
		);
		$this->track_event( static::EVENT_ADD_TO_CART, $data );
	}

	/**
	 * Used as a callback for the woocommerce_checkout_order_created hook.
	 *
	 * @since x.x.x
	 *
	 * @param string $order_id - WooCommerce order id.
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
		foreach ( $order->get_items() as $order_item ) {
			if ( ! method_exists( $order_item, 'get_product' ) ) {
				continue;
			}

			$product       = $order_item->get_product();
			$product_price = $product->get_price();

			$items[] = new Product(
				uniqid( 'product' ),
				$product->get_id(),
				$order_item->get_name(),
				wc_get_product_category_list( $product->get_id() ),
				'brand',
				$product_price,
				get_woocommerce_currency(),
				$order_item->get_quantity()
			);

			$total_quantity += $order_item->get_quantity();
		}

		$data = new Checkout(
			uniqid( 'checkout' ),
			$order_id,
			$order->get_total(),
			$total_quantity,
			$order->get_currency(),
			$items
		);
		$this->track_event( static::EVENT_CHECKOUT, $data );
	}

	/**
	 * Search event handler.
	 *
	 * @since x.x.x
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
	 * @since x.x.x
	 *
	 * @param string $event_name Tracking event name.
	 * @param Data   $data       Event Data object.
	 *
	 * @return void
	 */
	public function track_event( string $event_name, Data $data ) {
		foreach ( $this->get_trackers() as $tracker ) {
			// Skip Pinterest tag tracking if tag is not active.
			if ( $tracker instanceof Tag && ! Tag::get_active_tag() ) {
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
	 * @since x.x.x
	 *
	 * @return Tracker[]
	 */
	public function get_trackers() {
		return $this->trackers;
	}

	/**
	 * Adds a tracker to the array of trackers.
	 *
	 * @since x.x.x
	 *
	 * @param Tracker $tracker - One of objects implementing Tracker interface.
	 *
	 * @return void
	 */
	public function add_tracker( Tracker $tracker ) {
		$this->trackers[ get_class( $tracker ) ] = $tracker;
	}

	/**
	 * Removes a tracker.
	 *
	 * @since x.x.x
	 *
	 * @param string $tracker Tracker class name to be removed. e.g. Tag::class, Conversions::class.
	 *
	 * @return void
	 */
	public function remove_tracker( string $tracker ) {
		$this->trackers = array_filter(
			$this->trackers,
			function( $item ) use ( $tracker ) {
				return get_class( $item ) !== $tracker;
			}
		);
	}
}
