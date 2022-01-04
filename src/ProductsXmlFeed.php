<?php
/**
 * Pinterest for WooCommerce Catalog Syncing
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Pinterest\Product\Attributes\AttributeManager;
use WC_Product_Variation;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class adding Save Pin support.
 */
class ProductsXmlFeed {

	/**
	 * The default data structure of the Item to be printed in the XML feed.
	 *
	 * @var array
	 */
	private static $feed_item_structure = array(
		'g:id',
		'item_group_id',
		'title',
		'description',
		'g:product_type',
		'link',
		'g:image_link',
		'g:availability',
		'g:price',
		'sale_price',
		'g:mpn',
		'g:tax',
		'g:shipping',
		'g:additional_image_link',
	);

	private static $shipping = null;


	/**
	 * Returns the XML header to be printed.
	 *
	 * @return string
	 */
	public static function get_xml_header() {
		return '<?xml version="1.0"?>' . PHP_EOL . '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . PHP_EOL . "\t" . '<channel>' . PHP_EOL;
	}


	/**
	 * Returns the XML footer to be printed.
	 *
	 * @return string
	 */
	public static function get_xml_footer() {
		return "\t" . '</channel>' . PHP_EOL . '</rss>';
	}


	/**
	 * Returns the Item's XML for the given product.
	 *
	 * @param WC_Product $product The product to print the XML for.
	 *
	 * @return string
	 */
	public static function get_xml_item( $product ) {

		$xml = "\t\t<item>" . PHP_EOL;

		foreach ( apply_filters( 'pinterest_for_woocommerce_feed_item_structure', self::$feed_item_structure, $product ) as $attribute ) {
			$method_name = 'get_property_' . str_replace( ':', '_', $attribute );
			if ( method_exists( __CLASS__, $method_name ) ) {
				$att  = call_user_func_array( array( __CLASS__, $method_name ), array( $product, $attribute ) );
				$xml .= ! empty( $att ) ? "\t\t\t" . $att . PHP_EOL : '';
			}
		}

		$xml .= self::get_attributes_xml( $product, "\t\t\t" );

		$xml .= "\t\t</item>" . PHP_EOL;

		return apply_filters( 'pinterest_for_woocommerce_feed_item_xml', $xml, $product );
	}

	/**
	 * Get the XML for all the product attributes.
	 * Will only return the attributes which have been set
	 * or are available for the product type.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @param string     $indent  Line indentation string.
	 * @return string XML string.
	 */
	private static function get_attributes_xml( $product, $indent ) {
		$attribute_manager = AttributeManager::instance();
		$attributes        = $attribute_manager->get_all_values( $product );
		$xml               = '';

		// Merge with parent's attributes if it's a variation product.
		if ( $product instanceof WC_Product_Variation ) {
			$parent_product    = wc_get_product( $product->get_parent_id() );
			$parent_attributes = $attribute_manager->get_all_values( $parent_product );
			$attributes        = array_merge( $parent_attributes, $attributes );
		}

		foreach ( $attributes as $name => $value ) {
			$property = "g:{$name}";
			$value    = esc_html( $value );
			$xml     .= "{$indent}<{$property}>{$value}</{$property}>" . PHP_EOL;
		}

		return $xml;
	}

	/**
	 * Returns the Product ID.
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_g_id( $product, $property ) {
		return '<' . $property . '>' . $product->get_id() . '</' . $property . '>';
	}

	/**
	 * Returns the item_group_id (parent id for variations).
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_item_group_id( $product, $property ) {

		if ( ! $product->get_parent_id() ) {
			return;
		}

		return '<' . $property . '>' . $product->get_parent_id() . '</' . $property . '>';
	}


	/**
	 * Returns the product title.
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_title( $product, $property ) {
		return '<' . $property . '><![CDATA[' . $product->get_name() . ']]></' . $property . '>';
	}

	/**
	 * Returns the product description.
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_description( $product, $property ) {

		$description = $product->get_parent_id() ? $product->get_description() : $product->get_short_description();

		if ( empty( $description ) ) {
			$description = get_the_excerpt( $product->get_id() );
		}

		if ( empty( $description ) ) {
			return;
		}

		return '<' . $property . '><![CDATA[' . $description . ']]></' . $property . '>';
	}

	/**
	 * Returns the product taxonomies.
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_g_product_type( $product, $property ) {

		$id         = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$taxonomies = self::get_taxonomies( $id );

		if ( empty( $taxonomies ) ) {
			return;
		}

		return '<' . $property . '>' . implode( ' &gt; ', $taxonomies ) . '</' . $property . '>';
	}


	/**
	 * Returns the permalink.
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_link( $product, $property ) {
		return '<' . $property . '><![CDATA[' . $product->get_permalink() . ']]></' . $property . '>';
	}

	/**
	 * Returns the URL of the main product image.
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_g_image_link( $product, $property ) {

		$image_id = $product->get_image_id();

		if ( ! $image_id ) {
			return '';
		}

		$image = wp_get_attachment_image_src( $image_id, 'woocommerce_single' );

		if ( ! $image ) {
			return;
		}

		return '<' . $property . '><![CDATA[' . $image[0] . ']]></' . $property . '>';
	}


	/**
	 * Returns the availability of the product.
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_g_availability( $product, $property ) {

		switch ( $product->get_stock_status() ) {
			case 'instock':
				$stock_status = 'in stock';
				break;
			case 'outofstock':
				$stock_status = 'out of stock';
				break;
			case 'onbackorder':
				$stock_status = 'preorder';
				break;
			default:
				$stock_status = $product->get_stock_status();
				break;
		}

		return '<' . $property . '>' . $stock_status . '</' . $property . '>';
	}

	/**
	 * Returns the base price, or the min base price for a variable product.
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_g_price( $product, $property ) {

		if ( ! $product->get_parent_id() && method_exists( $product, 'get_variation_price' ) ) {
			$price = $product->get_variation_regular_price();
		} else {
			$price = $product->get_regular_price();
		}

		if ( empty( $price ) ) {
			return;
		}

		return '<' . $property . '>' . $price . get_woocommerce_currency() . '</' . $property . '>';
	}

	/**
	 * Returns the sale price of the product, or the min sale price for a variable product.
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_sale_price( $product, $property ) {

		if ( ! $product->get_parent_id() && method_exists( $product, 'get_variation_sale_price' ) ) {
			$regular_price = $product->get_variation_regular_price();
			$sale_price    = $product->get_variation_sale_price();
			$price         = $regular_price > $sale_price ? $sale_price : false;
		} else {
			$price = $product->get_sale_price();
		}

		if ( empty( $price ) ) {
			return;
		}

		return '<' . $property . '>' . $price . get_woocommerce_currency() . '</' . $property . '>';
	}

	/**
	 * Returns the SKU in order to populate the MPN field.
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_g_mpn( $product, $property ) {
		return '<' . $property . '>' . $product->get_sku() . '</' . $property . '>';
	}


	/**
	 * Returns the gallery images for the product.
	 *
	 * @param WC_Product $product the product.
	 * @param string     $property The name of the property.
	 *
	 * @return string
	 */
	private static function get_property_g_additional_image_link( $product, $property ) {

		$attachment_ids = $product->get_gallery_image_ids();
		$images         = array();

		if ( $attachment_ids && $product->get_image_id() ) {
			foreach ( $attachment_ids as $attachment_id ) {
				$images[] = wp_get_attachment_image_src( $attachment_id, 'woocommerce_single' )[0];
			}
		}

		if ( empty( $images ) ) {
			return;
		}

		return '<' . $property . '><![CDATA[' . implode( ',', $images ) . ']]></' . $property . '>';
	}

	private static function get_property_g_shipping( $product, $property ) {
		$column        = self::prepare_shipping_column( $product );
		return '<' . $property . '>' . $column . '</' . $property . '>';
	}

	/**
	 * Helper method to return the taxonomies of the product in a useful format.
	 *
	 * @param integer $product_id The product ID.
	 *
	 * @return array
	 */
	private static function get_taxonomies( $product_id ) {

		$terms = wc_get_object_terms( $product_id, 'product_cat' );

		if ( empty( $terms ) ) {
			return array();
		}

		return wp_list_pluck( $terms, 'name' );
	}

	private static function prepare_shipping_column( $product ) {
		$shipping = self::get_shipping_config();
		$lines    = array();
		foreach ( $shipping as $zone ) {
			$best_shipping = self::get_best_shipping_with_cost( $zone, $product );
			foreach( $zone->locations as $location ) {
				$currency = get_woocommerce_currency();
				$lines[]  = "$location->country:$location->state:$best_shipping->name:$best_shipping->cost $currency";
			}
		}
		return implode( ",", $lines );
	}

	private static function get_shipping_config() {
		if ( null !== self::$shipping ) {
			return self::$shipping;
		}
		$shipping       = new Shipping();
		self::$shipping = $shipping->get_shipping();
		return self::$shipping;
	}

	private static function get_best_shipping_with_cost( $zone, $product ) {
		$package = self::put_product_into_a_shipping_package( $product, reset( $zone->locations ) );
		$rates   = array();
		foreach ( $zone->shipping_methods as $shipping_method ) {
				// Use + instead of array_merge to maintain numeric keys.
				$rates += $shipping_method->get_rates_for_package( $package );
		}

		$best_cost = INF;
		$best_name = '';
		foreach ( $rates as $rate ) {
			$cost = $rate->get_cost();
			if ( $cost < $best_cost ) {
				$best_cost = $cost;
				$best_name = $rate->get_label();
			}
			$best_cost = $cost < $best_cost ? $cost : $best_cost;

		}
		return (object) array(
			'cost' => $best_cost,
			'name' => $best_name,
		);
	}

	public static function put_product_into_a_shipping_package( $product, $location ) {
		include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
		$cart_item = array(
			'key'          => 0,
			'product_id'   => $product->get_id(),
			'variation_id' => null,
			'variation'    => null,
			'quantity'     => 1,
			'data'         => $product,
			'data_hash'    => wc_get_cart_item_data_hash( $product ),
			'line_total'   => wc_remove_number_precision( (float) $product->get_price() ),
		);

		return array(
			'contents'        => array( $cart_item ),
			'contents_cost'   => (float) $product->get_price(),
			'applied_coupons' => array(),
			'user'            => array(
				'ID' => get_current_user_id(),
			),
			'destination'     => array(
				'country'   => $location->country,
				'state'     => $location->state,
				'postcode'  => '',
				'city'      => '',
				'address'   => '',
				'address_1' => '',
				'address_2' => '',
			),
			'cart_subtotal'   => (float) $product->get_price(),
		);
	}

}
