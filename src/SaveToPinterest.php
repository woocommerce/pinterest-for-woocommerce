<?php
/**
 * Pinterest For WooCommerce Pins
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
class SaveToPinterest {

	/**
	 * Initiate class.
	 */
	public static function maybe_init() {

		$show_loop_pins   = self::show_in_loop();
		$show_product_pin = self::show_in_product();

		if ( ! $show_loop_pins && ! $show_product_pin ) {
			return;
		}

		if ( $show_product_pin ) {
			add_action( 'woocommerce_before_single_product_summary', array( __CLASS__, 'render_product_pin' ) );
		}

		if ( $show_loop_pins ) {
			add_action( 'woocommerce_before_shop_loop_item', array( __CLASS__, 'render_product_pin' ), 1 );
		}
	}


	/**
	 * Show Product Pin HTML.
	 *
	 * @since 1.0.0
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
	 * @param int $post_id Post ID.
	 * @param int $post_thumbnail_id Optional. Post Thumbnail ID.
	 *
	 * @return string
	 */
	public static function render_pin( $post_id, $post_thumbnail_id = '' ) {

		$attributes = array(
			'description' => esc_html( get_the_title() ),
			'url'         => esc_url( get_the_permalink() ),
		);

		$post_thumbnail_id = empty( $post_thumbnail_id ) ? get_post_thumbnail_id( $post_id ) : $post_thumbnail_id;
		$attachment        = wp_get_attachment_image_src( $post_thumbnail_id, 'large' );
		if ( ! empty( $attachment ) ) {
			$attributes['media'] = esc_url( $attachment[0] );
		}

		//	Return HTML that will be replace by Pinterest
		return sprintf(
			'<a data-pin-do="buttonPin" href="%s"></a>',
			add_query_arg(
				$attributes,
				'https://www.pinterest.com/pin/create/button/'
			)
		);
	}


	/**
	 * Return if must show Save Pin in the loop
	 * 
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function show_in_loop() {

		static $show;

		if ( is_null( $show ) ) {

			/**
			 * Allow 3rd parties to enable or disable Save Pin feature for the loop.
			 *
			 * @since 1.0.0
			 *
			 * @param bool $is_enabled If true, Save Pin button will be present in the loop. Default: defined by setup.
			 */
			$show = apply_filters( 'pinterest_for_woocommerce_show_loop_pins', Pinterest_For_Woocommerce()::get_setting( 'show_loop_pins' ) );
		}

		return $show;
	}


	/**
	 * Return if must show Save Pin in the product single
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function show_in_product() {

		static $show;

		if ( is_null( $show ) ) {

			/**
			 * Allow 3rd parties to enable or disable Save Pin feature for product.
			 *
			 * @since 1.0.0
			 *
			 * @param bool $is_enabled If true, Save Pin button will be present in the product page. Default: defined by setup.
			 */
			$show = apply_filters( 'pinterest_for_woocommerce_show_product_pin', Pinterest_For_Woocommerce()::get_setting( 'show_product_pin' ) );
		}

		return $show;
	}
}
