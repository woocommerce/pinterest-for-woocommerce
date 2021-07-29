<?php
/**
 * Pinterest For WooCommerce Catalog Syncing
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

		$xml .= "\t\t</item>" . PHP_EOL;

		return apply_filters( 'pinterest_for_woocommerce_feed_item_xml', $xml, $product );
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

		$taxonomies = self::get_taxonomies( $product->get_id() );

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
				$images[] = wp_get_attachment_image_src( $attachment_id )[0];
			}
		}

		if ( empty( $images ) ) {
			return;
		}

		return '<' . $property . '><![CDATA[' . implode( ',', $images ) . ']]></' . $property . '>';
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
}
