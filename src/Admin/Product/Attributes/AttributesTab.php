<?php
/**
 * Class AttributesTab
 *
 * @package Automattic\WooCommerce\Pinterest\Admin\Product\Attributes
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Admin\Product\Attributes;

use Automattic\WooCommerce\Pinterest\Admin\Admin;
use Automattic\WooCommerce\Pinterest\Product\Attributes\AttributeManager;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Class AttributesTab
 */
class AttributesTab {

	/**
	 * Admin class.
	 *
	 * @var Admin
	 */
	protected $admin;

	/**
	 * Attribute manager.
	 *
	 * @var AttributeManager
	 */
	protected $attribute_manager;

	/**
	 * AttributesTab constructor.
	 *
	 * @param Admin $admin Admin class.
	 */
	public function __construct( Admin $admin ) {
		$this->admin             = $admin;
		$this->attribute_manager = AttributeManager::instance();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action(
			'woocommerce_new_product',
			function ( int $product_id, WC_Product $product ) {
				$this->handle_update_product( $product );
			},
			10,
			2
		);
		add_action(
			'woocommerce_update_product',
			function ( int $product_id, WC_Product $product ) {
				$this->handle_update_product( $product );
			},
			10,
			2
		);

		add_action(
			'woocommerce_product_data_tabs',
			function ( array $tabs ) {
				return $this->add_tab( $tabs );
			}
		);
		add_action(
			'woocommerce_product_data_panels',
			function () {
				$this->render_panel();
			}
		);
	}

	/**
	 * Adds the Pinterest tab to the WooCommerce product data box.
	 *
	 * @param array $tabs The current product data tabs.
	 *
	 * @return array An array with product tabs with the Pinterest tab added.
	 */
	private function add_tab( array $tabs ): array {
		$shown_types = array_map(
			function ( string $product_type ) {
				return "show_if_${product_type}";
			},
			$this->get_applicable_product_types()
		);

		$classes = array_merge( array( 'pinterest' ), $shown_types );

		$tabs['pinterest_attributes'] = array(
			'label'  => 'Pinterest',
			'class'  => join( ' ', $classes ),
			'target' => 'pinterest_attributes',
		);

		return $tabs;
	}

	/**
	 * Render the product attributes tab.
	 */
	private function render_panel() {
		$product = wc_get_product( get_the_ID() );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->admin->get_view(
			'attributes/tab-panel',
			array(
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'form' => $this->get_form( $product )->get_view_data(),
			)
		);
	}

	/**
	 * Handle form submission and update the product attributes.
	 *
	 * @param WC_Product $product WooCommerce product.
	 */
	private function handle_update_product( WC_Product $product ) {
		/**
		 * Array of `true` values for each product IDs already handled by this method. Used to prevent double submission.
		 *
		 * @var bool[] $already_updated
		 */
		static $already_updated = array();
		if ( isset( $already_updated[ $product->get_id() ] ) ) {
			return;
		}

		$form           = $this->get_form( $product );
		$form_view_data = $form->get_view_data();

		// phpcs:disable WordPress.Security.NonceVerification
		if ( empty( $_POST[ $form_view_data['name'] ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$submitted_data = (array) wc_clean( wp_unslash( $_POST[ $form_view_data['name'] ] ) );
		// phpcs:enable WordPress.Security.NonceVerification

		$form->submit( $submitted_data );
		$this->update_data( $product, $form->get_data() );

		$already_updated[ $product->get_id() ] = true;
	}

	/**
	 * Get the attributes form.
	 *
	 * @param WC_Product $product WooCommerce product.
	 *
	 * @return AttributesForm
	 */
	protected function get_form( WC_Product $product ): AttributesForm {
		$attribute_types = $this->attribute_manager->get_attribute_types_for_product_types( $this->get_applicable_product_types() );

		$form = new AttributesForm( $attribute_types, $this->attribute_manager->get_all_values( $product ) );
		$form->set_name( 'attributes' );

		return $form;
	}

	/**
	 * Return an array of WooCommerce product types that the Pinterest tab can be displayed for.
	 *
	 * @return array of WooCommerce product types (e.g. 'simple', 'variable', etc.)
	 */
	protected function get_applicable_product_types(): array {
		return apply_filters( 'wc_pinterest_attributes_tab_applicable_product_types', array( 'simple', 'variable' ) );
	}

	/**
	 * Update data.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @param array      $data    Data to update.
	 *
	 * @return void
	 */
	protected function update_data( WC_Product $product, array $data ): void {
		foreach ( $this->attribute_manager->get_attribute_types_for_product( $product ) as $attribute_id => $attribute_type ) {
			$value = isset( $data[ $attribute_id ] ) ? $data[ $attribute_id ] : null;
			$this->attribute_manager->update( $product, new $attribute_type( $value ) );
		}
	}

}
