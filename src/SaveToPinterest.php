<?php
/**
 * Pinterest for WooCommerce Pins
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

use \Pinterest_For_Woocommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class adding Save Pin support.
 *
 * @class   SaveToPinterest
 * @version x.x.x
 */
class SaveToPinterest {

	/**
	 * Initiate class.
	 *
	 * @return void
	 */
	public static function maybe_init() {

		if ( ! Pinterest_For_Woocommerce::is_setup_complete() || ! self::show_pin_button() ) {
			return;
		}

		add_action( 'woocommerce_before_single_product_summary', array( __CLASS__, 'render_product_pin' ) );
		add_action( 'woocommerce_before_shop_loop_item', array( __CLASS__, 'render_product_pin' ), 1 );
		add_filter( 'woocommerce_blocks_product_grid_item_html', array( __CLASS__, 'add_to_wc_blocks' ), 10, 3 );
		add_filter( 'render_block_woocommerce/all-products', array( __CLASS__, 'add_to_all_products' ) );

		// Enqueue our JS files.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}


	/**
	 * Show Product Pin HTML.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_product_pin() {

		global $product;

		if ( empty( $product ) ) {
			return;
		}

		echo wp_kses_post( self::render_pin( $product->get_id() ) );
	}


	/**
	 * Show Pin HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param int        $post_id           Post ID.
	 * @param int|string $post_thumbnail_id Optional. Post Thumbnail ID.
	 *
	 * @return string
	 */
	public static function render_pin( $post_id, $post_thumbnail_id = '' ) {

		$product_name = get_the_title( $post_id );

		/* translators: %s: product name */
		$screen_reader_text = esc_html( sprintf( __( '%s to Pinterest', 'pinterest-for-woocommerce' ), $product_name ) );

		$product = wc_get_product( $post_id );
		if ( $product && $product->get_image_id() ) {
			$screen_reader_text .= '<span class="pinterest-for-woocommerce-new-window-hint"> '
				. esc_html__( '(opens in a new window)', 'pinterest-for-woocommerce' )
				. '</span>';
		}

		/*
		 * pinit.js reads these from the link's query string, which it splits on "?", "&" and "="
		 * before decoding each value. Every value is therefore plain text, percent-encoded below:
		 * an unencoded "&" in a product name or "?" in a plain permalink would cut the value short.
		 */
		$attributes = array(
			'description' => html_entity_decode( $product_name, ENT_QUOTES, 'UTF-8' ),
			'url'         => (string) get_the_permalink( $post_id ),
		);

		$post_thumbnail_id = empty( $post_thumbnail_id ) ? get_post_thumbnail_id( $post_id ) : $post_thumbnail_id;
		$attachment        = wp_get_attachment_image_src( $post_thumbnail_id, 'large' );
		if ( ! empty( $attachment ) ) {
			$attributes['media'] = (string) $attachment[0];
		}

		/**
		 * Return HTML that will be replaced by the Pinterest button.
		 *
		 * Documentation: https://developers.pinterest.com/docs/widgets/save/
		 * Here we set the 'pin-do' data attribute to 'buttonPin' so that the
		 * Image used is the one explicitly set in the media attribute.
		 */
		return sprintf(
			'<div class="pinterest-for-woocommerce-image-wrapper"><span class="screen-reader-text">%s</span><a data-pin-do="buttonPin" href="%s"></a></div>',
			$screen_reader_text,
			esc_url(
				add_query_arg(
					array_map( 'rawurlencode', $attributes ),
					'https://www.pinterest.com/pin/create/button/'
				)
			)
		);
	}


	/**
	 * Return if we must show the Save Pin button.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function show_pin_button() {
		return (bool) Pinterest_For_Woocommerce()::get_setting( 'save_to_pinterest' );
	}


	/**
	 * Add the Pinit button's markup to WC blocks
	 *
	 * @since 1.0.0
	 *
	 * @param string      $html The html to be filtered.
	 * @param Object      $data Data passed to the filter.
	 * @param \WC_Product $product The product object.
	 *
	 * @return string
	 */
	public static function add_to_wc_blocks( $html, $data, $product ) {

		if ( empty( $product ) ) {
			return $html;
		}

		return str_replace( '</li>', self::render_pin( $product->get_id() ) . '</li>', $html );
	}

	/**
	 * Add a client-rendered child to the All Products card layout.
	 *
	 * @param mixed $html Rendered block markup.
	 * @return mixed
	 */
	public static function add_to_all_products( $html ) {
		if ( ! is_string( $html ) || ! self::show_pin_button() ) {
			return $html;
		}

		$processor = new \WP_HTML_Tag_Processor( $html );
		if ( ! $processor->next_tag( array( 'class_name' => 'wp-block-woocommerce-all-products' ) ) ) {
			return $html;
		}
		$encoded_attributes = $processor->get_attribute( 'data-attributes' );
		$attributes         = is_string( $encoded_attributes ) ? json_decode( $encoded_attributes, true ) : null;
		if ( ! is_array( $attributes ) || ! isset( $attributes['layoutConfig'] ) || ! is_array( $attributes['layoutConfig'] ) ) {
			return $html;
		}

		$asset_path = Pinterest_For_Woocommerce()->plugin_path() . '/assets/build/all-products-pin.asset.php';
		$asset      = file_exists( $asset_path ) ? include $asset_path : array(
			'dependencies' => array( 'wc-blocks-registry', 'react-jsx-runtime', 'wp-html-entities', 'wp-i18n' ),
			'version'      => PINTEREST_FOR_WOOCOMMERCE_VERSION,
		);
		wp_enqueue_script(
			PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-all-products-pin',
			Pinterest_For_Woocommerce()->plugin_url() . '/assets/build/all-products-pin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-all-products-pin', 'pinterest-for-woocommerce' );
		foreach ( $attributes['layoutConfig'] as $child ) {
			if ( is_array( $child ) && 'pinterest-for-woocommerce/product-pin' === ( $child[0] ?? '' ) ) {
				return $html;
			}
		}
		$attributes['layoutConfig'][] = array( 'pinterest-for-woocommerce/product-pin', array() );
		$processor->set_attribute( 'data-attributes', wp_json_encode( $attributes ) );

		return $processor->get_updated_html();
	}


	/**
	 * Enqueue JS files necessary to properly track actions such as search.
	 *
	 * @return void
	 */
	public static function enqueue_scripts() {

		$ext = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_enqueue_script(
			PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-save-button',
			Pinterest_For_Woocommerce()->plugin_url() . '/assets/js/pinterest-for-woocommerce-save-button' . $ext . '.js',
			array(),
			PINTEREST_FOR_WOOCOMMERCE_VERSION,
			true
		);
	}

}
