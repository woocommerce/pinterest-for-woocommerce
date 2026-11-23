<?php
/**
 * Stand-in for a WooCommerce Composite Products composite.
 *
 * @package Pinterest_For_WooCommerce/Tests
 */

/**
 * A product of type 'composite' with the composite price methods.
 *
 * Its minimum composite prices are read from product meta, so an instance
 * loaded again through wc_get_product() keeps them. The container price stays
 * empty, as on a composite priced by its components.
 *
 * @version x.x.x
 */
class CompositeProductStub extends WC_Product_Simple {

	/**
	 * Meta key holding the minimum active composite price.
	 *
	 * @var string
	 */
	const MIN_PRICE_META = '_pinterest_test_composite_min_price';

	/**
	 * Meta key holding the minimum regular composite price.
	 *
	 * @var string
	 */
	const MIN_REGULAR_PRICE_META = '_pinterest_test_composite_min_regular_price';

	/**
	 * Product type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'composite';
	}

	/**
	 * Minimum or maximum composite price.
	 *
	 * @param string $min_or_max 'min' or 'max'.
	 * @param bool   $display    Whether to adjust the price for display.
	 *
	 * @return string
	 */
	public function get_composite_price( $min_or_max = 'min', $display = false ) {
		return (string) $this->get_meta( self::MIN_PRICE_META );
	}

	/**
	 * Minimum or maximum composite regular price.
	 *
	 * @param string $min_or_max 'min' or 'max'.
	 * @param bool   $display    Whether to adjust the price for display.
	 *
	 * @return string
	 */
	public function get_composite_regular_price( $min_or_max = 'min', $display = false ) {
		return (string) $this->get_meta( self::MIN_REGULAR_PRICE_META );
	}

	/**
	 * Makes wc_get_product() load products of type 'composite' as this class.
	 *
	 * WP_UnitTestCase restores hooks after each test, so the filter does not
	 * outlive the test that registers it.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter(
			'woocommerce_product_class',
			function ( $classname, $product_type ) {
				return 'composite' === $product_type ? self::class : $classname;
			},
			10,
			2
		);
	}

	/**
	 * Creates and saves a composite with an empty container price.
	 *
	 * @param string $min_price         Minimum active composite price, '' for none.
	 * @param string $min_regular_price Minimum regular composite price, '' for none.
	 * @param array  $props             Extra product properties.
	 *
	 * @return self
	 */
	public static function create( $min_price, $min_regular_price, array $props = array() ) {
		self::register();

		$product = new self();
		$product->set_props(
			array_merge(
				array(
					'name'          => 'Composite print',
					'regular_price' => '',
					'price'         => '',
					'sku'           => 'COMPOSITE-' . wp_generate_password( 6, false ),
					'stock_status'  => 'instock',
				),
				$props
			)
		);
		$product->update_meta_data( self::MIN_PRICE_META, $min_price );
		$product->update_meta_data( self::MIN_REGULAR_PRICE_META, $min_regular_price );
		// Saving stores the 'composite' product_type term from get_type().
		$product->save();

		return $product;
	}
}
