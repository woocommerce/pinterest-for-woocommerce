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

	/**
	 * Shipping object. Used for caching between calls to the shipping column function.
	 *
	 * @var Shipping|null $shipping
	 */
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

		return '<' . $property . '>' . wc_format_decimal( $price, self::get_currency_decimals() ) . get_woocommerce_currency() . '</' . $property . '>';
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

		return '<' . $property . '>' . wc_format_decimal( $price, self::get_currency_decimals() ) . get_woocommerce_currency() . '</' . $property . '>';
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

	/**
	 * Returns the product shipping information.
	 *
	 * @since x.x.x
	 *
	 * @param WC_Product $product  The product.
	 * @param string     $property The name of the property.
	 * @return string
	 */
	private static function get_property_g_shipping( $product, $property ) {
		$currency      = get_woocommerce_currency();
		$entries       = array();
		$shipping      = self::get_shipping();
		$shipping_info = $shipping->prepare_shipping_info( $product );

		if ( empty( $shipping_info ) ) {
			return '';
		}

		/*
		 * Entry is a comma separated string with values in the following format:
		 *   COUNTRY:STATE:POST_CODE:SHIPPING_COST
		 */
		foreach ( $shipping_info as $info ) {
			$entries[] = "$info[country]:$info[state]:$info[name]:$info[cost] $currency";
		}

		return '<' . $property . '>' . implode( ',', $entries ) . '</' . $property . '>';
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

	/**
	 * Get locale currency decimals
	 */
	private static function get_currency_decimals() {
		$locale_info = include WC()->plugin_path() . '/i18n/locale-info.php';
		$country     = Pinterest_For_Woocommerce()::get_base_country() ?? 'US';

		return $locale_info[ $country ]['num_decimals'] ?? wc_get_price_decimals();
	}

	/** Fetch shipping object.
	 *
	 * @since x.x.x
	 *
	 * @return Shipping
	 */
	private static function get_shipping() {
		if ( null === self::$shipping ) {
			self::$shipping = new Shipping();
			/**
			 * When we start generating lets make sure that the cart is loaded.
			 * Various shipping and tax functions are using elements of cart.
			 */
			wc_load_cart();
		}
		return self::$shipping;
	}
}
