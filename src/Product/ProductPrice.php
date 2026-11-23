<?php
/**
 * Resolves the product prices sent to Pinterest.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 */

namespace Automattic\WooCommerce\Pinterest\Product;

use WC_Cart;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the active, regular and add-to-cart prices that the product feed,
 * Rich Pins and tracking events send to Pinterest.
 *
 * WooCommerce Composite Products containers have no price of their own: their
 * price comes from their components. Composites are priced at their "from"
 * price, and an AddToCart of a composite reports the price of the configured
 * container and components in the cart.
 *
 * @since x.x.x
 * @version x.x.x
 */
class ProductPrice {

	/**
	 * Context for prices in the product feed.
	 *
	 * @var string
	 */
	const CONTEXT_FEED = 'feed';

	/**
	 * Context for prices in Rich Pins tags.
	 *
	 * @var string
	 */
	const CONTEXT_RICH_PINS = 'rich_pins';

	/**
	 * Context for the PageVisit event value.
	 *
	 * @var string
	 */
	const CONTEXT_PAGE_VISIT = 'page_visit';

	/**
	 * Context for the AddToCart event value.
	 *
	 * @var string
	 */
	const CONTEXT_ADD_TO_CART = 'add_to_cart';

	/**
	 * Returns true when the product is a WooCommerce Composite Products composite.
	 *
	 * Composites are recognised by their type and composite price methods, so
	 * this works without referencing a Composite Products class.
	 *
	 * @since x.x.x
	 *
	 * @param mixed $product Product to check.
	 *
	 * @return bool
	 */
	public static function is_composite( $product ): bool {
		return $product instanceof WC_Product
			&& $product->is_type( 'composite' )
			&& is_callable( array( $product, 'get_composite_price' ) )
			&& is_callable( array( $product, 'get_composite_regular_price' ) );
	}

	/**
	 * Returns the active display price of a product.
	 *
	 * Composites use their minimum composite price. Other products use
	 * wc_get_price_to_display().
	 *
	 * @since x.x.x
	 *
	 * @param WC_Product $product Product.
	 * @param string     $context Where the price is used, one of the CONTEXT_* constants.
	 *
	 * @return mixed Price.
	 */
	public static function get_price( WC_Product $product, string $context ) {
		$price = self::get_composite_min_price( $product, 'get_composite_price', true );
		if ( null === $price ) {
			$price = wc_get_price_to_display( $product );
		}

		return self::filter_price( $price, $product, $context );
	}

	/**
	 * Returns the regular display price of a product.
	 *
	 * Variable products use their minimum variation regular price and composites
	 * their minimum composite regular price. Other products use their regular
	 * price, adjusted for display.
	 *
	 * @since x.x.x
	 *
	 * @param WC_Product $product Product.
	 * @param string     $context Where the price is used, one of the CONTEXT_* constants.
	 *
	 * @return mixed Regular price.
	 */
	public static function get_regular_price( WC_Product $product, string $context ) {
		if ( ! $product->get_parent_id() && is_callable( array( $product, 'get_variation_regular_price' ) ) ) {
			$price = $product->get_variation_regular_price( 'min', true );
		} else {
			$price = self::get_composite_min_price( $product, 'get_composite_regular_price', true );
			if ( null === $price ) {
				$price = wc_get_price_to_display(
					$product,
					array(
						'price' => $product->get_regular_price(),
					)
				);
			}
		}

		return self::filter_regular_price( $price, $product, $context );
	}

	/**
	 * Returns the price of one unit of a product added to the customer's cart.
	 *
	 * A composite container in the cart is priced as its own display price plus
	 * the display price of its components, per container. Other cart items use
	 * the product's display price.
	 *
	 * @since x.x.x
	 *
	 * @param WC_Product $product       Product that was added to the cart.
	 * @param string     $cart_item_key Key of the cart item that was added.
	 *
	 * @return mixed Price.
	 */
	public static function get_add_to_cart_price( WC_Product $product, string $cart_item_key ) {
		$price = self::get_composite_cart_item_price( $cart_item_key );
		if ( null === $price ) {
			$price = wc_get_price_to_display( $product );
		}

		return self::filter_price( $price, $product, self::CONTEXT_ADD_TO_CART, $cart_item_key );
	}

	/**
	 * Returns the price used to estimate shipping for a product.
	 *
	 * Composites use their minimum composite price, without display tax
	 * adjustments, because their container price is empty. Other products use
	 * their price.
	 *
	 * @since x.x.x
	 *
	 * @param WC_Product $product Product.
	 *
	 * @return float Price.
	 */
	public static function get_shipping_price( WC_Product $product ): float {
		$price = self::get_composite_min_price( $product, 'get_composite_price', false );

		return (float) ( null === $price ? $product->get_price() : $price );
	}

	/**
	 * Applies the active price filter, keeping the price when the filtered value is invalid.
	 *
	 * @since x.x.x
	 *
	 * @param mixed      $price         Price.
	 * @param WC_Product $product       Product.
	 * @param string     $context       Where the price is used, one of the CONTEXT_* constants.
	 * @param string     $cart_item_key Cart item key for the add_to_cart context, otherwise empty.
	 *
	 * @return mixed Price.
	 */
	public static function filter_price( $price, WC_Product $product, string $context, string $cart_item_key = '' ) {
		/**
		 * Filters the active price of a product sent to Pinterest.
		 *
		 * Used by the product feed sale price, Rich Pins, and the PageVisit and
		 * AddToCart events. A value that is neither numeric nor an empty string is
		 * ignored.
		 *
		 * @since x.x.x
		 *
		 * @param mixed       $price         Price.
		 * @param \WC_Product $product       Product.
		 * @param string      $context       One of 'feed', 'rich_pins', 'page_visit' or 'add_to_cart'.
		 * @param string      $cart_item_key Cart item key for 'add_to_cart', otherwise empty.
		 */
		$filtered = apply_filters( 'pinterest_for_woocommerce_product_price', $price, $product, $context, $cart_item_key );

		return self::is_valid_price( $filtered ) ? $filtered : $price;
	}

	/**
	 * Applies the regular price filter, keeping the price when the filtered value is invalid.
	 *
	 * @since x.x.x
	 *
	 * @param mixed      $price   Regular price.
	 * @param WC_Product $product Product.
	 * @param string     $context Where the price is used, one of the CONTEXT_* constants.
	 *
	 * @return mixed Regular price.
	 */
	public static function filter_regular_price( $price, WC_Product $product, string $context ) {
		/**
		 * Filters the regular price of a product sent to Pinterest.
		 *
		 * Used by the product feed price and the Rich Pins standard price. A value
		 * that is neither numeric nor an empty string is ignored.
		 *
		 * @since x.x.x
		 *
		 * @param mixed       $price   Regular price.
		 * @param \WC_Product $product Product.
		 * @param string      $context One of 'feed' or 'rich_pins'.
		 */
		$filtered = apply_filters( 'pinterest_for_woocommerce_product_regular_price', $price, $product, $context );

		return self::is_valid_price( $filtered ) ? $filtered : $price;
	}

	/**
	 * Returns a composite's minimum price, or null when the product is not a
	 * composite or Composite Products has no price for it.
	 *
	 * Composite Products returns an empty price when no configuration is
	 * purchasable, when the catalog price is hidden, or while its prices are
	 * still being calculated.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $method  Composite price method.
	 * @param bool       $display Whether to adjust the price for display.
	 *
	 * @return mixed|null Price, or null.
	 */
	private static function get_composite_min_price( WC_Product $product, string $method, bool $display ) {
		if ( ! self::is_composite( $product ) ) {
			return null;
		}

		$price = $product->$method( 'min', $display );

		return is_numeric( $price ) ? $price : null;
	}

	/**
	 * Returns the price of one composite in the customer's cart, or null when the
	 * cart item is not a composite container.
	 *
	 * Composite Products adds the component cart items before the
	 * woocommerce_add_to_cart callbacks at the default priority run, and lists
	 * their keys in the container's composite_children.
	 *
	 * @param string $cart_item_key Cart item key.
	 *
	 * @return float|null Price, or null.
	 */
	private static function get_composite_cart_item_price( string $cart_item_key ) {
		// The cart is null until WooCommerce loads it for the request.
		$cart = function_exists( 'WC' ) ? WC()->cart : null;
		if ( ! $cart instanceof WC_Cart ) {
			return null;
		}

		$container = $cart->get_cart_item( $cart_item_key );
		if (
			empty( $container['composite_data'] )
			|| ! isset( $container['composite_children'] )
			|| ! is_array( $container['composite_children'] )
			|| ! isset( $container['data'] )
			|| ! $container['data'] instanceof WC_Product
			|| empty( $container['quantity'] )
		) {
			return null;
		}

		$container_quantity = (float) $container['quantity'];
		$price              = (float) wc_get_price_to_display( $container['data'] );

		foreach ( $container['composite_children'] as $child_key ) {
			$child = $cart->get_cart_item( $child_key );
			if ( empty( $child['quantity'] ) || ! isset( $child['data'] ) || ! $child['data'] instanceof WC_Product ) {
				continue;
			}

			$price += (float) wc_get_price_to_display( $child['data'] ) * ( (float) $child['quantity'] / $container_quantity );
		}

		return round( $price, wc_get_price_decimals() );
	}

	/**
	 * Returns true for a value accepted from a price filter.
	 *
	 * @param mixed $price Filtered price.
	 *
	 * @return bool
	 */
	private static function is_valid_price( $price ): bool {
		return '' === $price || is_numeric( $price );
	}
}
