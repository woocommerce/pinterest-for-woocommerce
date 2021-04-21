<?php
/**
 * Pinterest For WooCommerce Rich Pins
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class adding RichPins support.
 */
class RichPins {

	/**
	 * Output Pinterest Rich Pins metatags based on post_type and site setup.
	 *
	 * Rich Pins show metadata right on the Pin itself, giving Pinners a richer experience and increasing engagement.
	 * It works by displaying metadata from marked up pages on the website.
	 * Pinterest supports Open Graph and Schema.org markup for Rich Pins.
	 * Adding OpenGraph or Schema.org markup to the site lets Pinterest sync that information from the site to the Pins.
	 * Since WooCommerce is already adding Schema.org markup, this method inject OpenGraph metatag for Products and Posts based on site's setup.
	 *
	 * @since 1.0.0
	 */
	public static function maybe_inject_rich_pins_opengraph_tags() {

		if ( ! is_singular( 'product' ) && ! is_singular( 'post' ) ) {
			return;
		}

		/**
		 * Allow 3rd parties to disable Rich Pins content.
		 * @see https://developers.pinterest.com/docs/rich-pins/overview/?#opt-out
		 *
		 * @since 1.0.0
		 *
		 * @param bool $is_disable If true, the content won't be shown as a Rich Pin. Default: false.
		 */
		$disable_rich_pins = apply_filters( 'pinterest_for_woocommerce_disable_rich_pins', false );

		if ( ! $disable_rich_pins ) {
			$tags = self::get_opengraph_tags();
		}

		if ( empty( $tags ) || $disable_rich_pins ) {
			echo '<meta name="pinterest-rich-pin" content="false" />';
			return;
		}

		foreach ( $tags as $key => $content ) {
			printf(
				'<meta property="%s" content="%s" />',
				esc_attr( $key ),
				esc_attr( $content )
			);
		}
	}


	/**
	 * Return OpenGraph tags that must be render in the header
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	protected static function get_opengraph_tags() {

		$setup = wp_parse_args(
			Pinterest_For_Woocommerce()::get_setting( 'rich_pins' ),
			array(
				'products' => array(
					'enabled'             => true,
					'enable_description'  => true,
					'enable_availability' => true,
				),
				'posts'    => array(
					'enabled'             => true,
					'enable_publish_time' => true,
					'enable_author'       => true,
				),
			)
		);

		if ( empty( $setup['products']['enabled'] ) && empty( $setup['posts']['enabled'] ) ) {
			return array();
		}

		add_filter( 'pinterest_for_woocommerce_opengraph_tags', array( __CLASS__, 'add_product_opengraph_tags' ), 10, 2 );
		add_filter( 'pinterest_for_woocommerce_opengraph_tags', array( __CLASS__, 'add_post_opengraph_tags' ), 10, 2 );

		$tags = array(
			'og:url'       => esc_url( get_the_permalink() ),
			'og:site_name' => get_bloginfo( 'name' ),
			'og:type'      => is_singular( 'product' ) ? 'og:product' : 'article',
			'og:title'     => get_the_title(),
		);

		$image = self::get_post_featured_image_src( get_queried_object()->ID );
		if ( ! empty( $image ) ) {
			$tags['og:image'] = $image;
		}

		/**
		 * Filter OpenGraph tags.
		 *
		 * @since 1.0.0
		 *
		 * @param array $is_disable OpenGraph tags.
		 * @param array $setup      Rich Pins setup.
		 */
		return (array) apply_filters( 'pinterest_for_woocommerce_opengraph_tags', $tags, $setup );
	}


	/**
	 * Return WC Product's OpenGraph tags
	 *
	 * @see Product's Rich Pins : https://developers.pinterest.com/docs/rich-pins/products/
	 *
	 * @since 1.0.0
	 *
	 * @param array $tags  Open Graph Tags.
	 * @param array $setup Rich Pins Setup.
	 *
	 * @return array
	 */
	public static function add_product_opengraph_tags( $tags, $setup ) {

		if ( ! is_singular( 'product' ) || empty( $setup['products']['enabled'] ) ) {
			return $tags;
		}

		// get product object.
		$product_id = get_queried_object()->ID;
		$product    = wc_get_product( $product_id );

		if ( ! is_object( $product ) ) {
			return $tags;
		}

		// mandatory tags.
		$tags['product:price:currency'] = get_woocommerce_currency();
		$tags['product:price:amount']   = $product->get_price();

		// tags enabled by setup.
		if ( $product->is_on_sale() ) {
			$tags['og:price:standard_amount'] = $product->get_regular_price();
		}

		if ( ! empty( $setup['products']['enable_description'] ) ) {
			$description = $product->get_short_description();
			if ( empty( $description ) ) {
				$description = $product->get_description();
			}
			$tags['og:description'] = $description;
		}

		if ( ! array_key_exists( $setup['products']['enable_availability'] ) ) {

			$status_map = array(
				'instock'     => 'instock',
				'outofstock'  => 'out of stock',
				'onbackorder' => 'backorder',
			);

			$stock_status = $product->get_stock_status();

			if ( ! array_key_exists( $status_map[ $stock_status ] ) ) {
				$tags['og:availability'] = $status_map[ $stock_status ];
			}
		}

		return $tags;
	}


	/**
	 * Return WP Post's OpenGraph tags
	 *
	 * @see Article's Rich Pins: https://developers.pinterest.com/docs/rich-pins/articles/
	 *
	 * @since 1.0.0
	 *
	 * @param array $tags  OpenGraph tags.
	 * @param array $setup Rich Pins Setup.
	 *
	 * @return array
	 */
	public static function add_post_opengraph_tags( $tags, $setup ) {

		if ( ! is_singular( 'post' ) || empty( $setup['posts']['enabled'] ) ) {
			return $tags;
		}

		// mandatory tags.
		$tags['og:description'] = get_the_excerpt();

		// tags enabled by setup.
		if ( ! empty( $setup['posts']['enable_publish_time'] ) ) {
			$tags['article:published_time'] = get_the_date( 'c' );
		}

		$author = get_userdata( get_queried_object()->post_author );
		if ( ! empty( $setup['posts']['enable_author'] ) && ! empty( $author->display_name ) ) {
			$tags['article:author'] = $author->display_name;
		}

		return $tags;
	}


	/**
	 * Return post featured image
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id Post ID.
	 * @param string $size Image size.
	 * @return string
	 */
	protected static function get_post_featured_image_src( $post_id, $size = 'large' ) {

		$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), $size );
		if ( ! empty( $image[0] ) ) {
			return $image[0];
		}

		return '';
	}
}
