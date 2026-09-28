<?php
/**
 * Attribute form values at the submission boundary.
 *
 * @package Automattic\WooCommerce\Pinterest\Tests
 */

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Admin\Product\Attributes;

use Automattic\WooCommerce\Pinterest\Admin\Product\Attributes\AttributesForm;
use Automattic\WooCommerce\Pinterest\Admin\Input\Form;
use Automattic\WooCommerce\Pinterest\Product\Attributes\Condition;
use Automattic\WooCommerce\Pinterest\Product\Attributes\GoogleCategory;
use Automattic\WooCommerce\Pinterest\Tests\Helpers\GoogleCategoryWithValueOptions;
use Automattic\WooCommerce\Pinterest\View\PHPViewFactory;
use WP_UnitTestCase;

require_once dirname( __DIR__, 4 ) . '/Helpers/GoogleCategoryWithValueOptions.php';

/**
 * Verify the existing form's names and submitted values.
 */
class AttributesFormTest extends WP_UnitTestCase {
	/**
	 * Non-select attributes can receive options from their class or the public filter.
	 *
	 * @dataProvider value_options_forms
	 * @param string $attribute_type Attribute class.
	 * @param bool   $variation Whether to render a variation form.
	 * @param string $submitted_value Submitted field value.
	 * @param string $expected_value Accepted field value.
	 * @param string $initial_value Stored field value.
	 * @param bool   $use_filter Whether to supply filtered options.
	 */
	public function test_non_select_value_options( $attribute_type, $variation, $submitted_value = ' 166 ', $expected_value = '166', $initial_value = '2271', $use_filter = true ) {
		require_once WC_ABSPATH . 'includes/admin/wc-meta-box-functions.php';

		$filter = function ( $options ) {
			return $options + array(
				'2271' => 'Apparel',
				'166'  => 'Accessories',
			);
		};
		if ( $use_filter ) {
			add_filter( 'wc_pinterest_product_attribute_value_options_google_product_category', $filter );
		}
		try {
			$attributes = new AttributesForm( array( $attribute_type ), array( 'google_product_category' => $initial_value ) );
			$attributes->set_name( $variation ? '3' : 'attributes' );
			$form = $attributes;
			if ( $variation ) {
				$form = new Form();
				$form->set_name( 'variation_attributes' )->add( $attributes );
			}
			$input = $attributes->get_view_data()['children']['google_product_category'];
			$name  = $variation ? 'pinterest_variation_attributes[3][google_product_category]' : 'pinterest_attributes[google_product_category]';
			$this->assertSame( 'select', $input['type'] );
			$this->assertSame( $name, $input['name'] );
			$this->assertSame( 'Google Category', $input['label'] );
			$this->assertNotEmpty( $input['description'] );
			$this->assertSame( 'Default', $input['options'][''] );
			if ( $use_filter ) {
				$this->assertSame( 'Apparel', $input['options'][2271] );
				$this->assertSame( 'Accessories', $input['options'][166] );
			}
			if ( GoogleCategoryWithValueOptions::class === $attribute_type ) {
				$this->assertSame( 'From attribute', $input['options'][1] );
			}

			$view    = ( new PHPViewFactory() )->create( $variation ? 'attributes/variations-form' : 'attributes/tab-panel' );
			$context = $variation ? $form->get_view_data() : array( 'form' => $form->get_view_data() );
			$html    = $view->render( $context );
			$this->assertStringContainsString( 'name="' . $name . '"', $html );
			$document = new \DOMDocument();
			$document->loadHTML( $html );
			$selected = ( new \DOMXPath( $document ) )->query( '//select[@name="' . $name . '"]/option[@selected]' );
			$this->assertCount( 1, $selected );
			$this->assertSame( $initial_value, $selected->item( 0 )->getAttribute( 'value' ) );
			if ( $variation ) {
				$this->assertStringContainsString( 'form-row form-row-full', $html );
			}

			$submitted = array( 'google_product_category' => $submitted_value );
			$expected  = array( 'google_product_category' => $expected_value );
			$form->submit( $variation ? array( 3 => $submitted ) : $submitted );
			$this->assertSame( $variation ? array( 3 => $expected ) : $expected, $form->get_data() );
		} finally {
			if ( $use_filter ) {
				remove_filter( 'wc_pinterest_product_attribute_value_options_google_product_category', $filter );
			}
		}
	}

	/**
	 * Both form views and both supported sources of value options.
	 *
	 * @return array
	 */
	public function value_options_forms() {
		return array(
			'simple filter'       => array( GoogleCategory::class, false ),
			'variation filter'    => array( GoogleCategory::class, true ),
			'simple interface'    => array( GoogleCategoryWithValueOptions::class, false ),
			'variation interface' => array( GoogleCategoryWithValueOptions::class, true ),
			'simple default'      => array( GoogleCategory::class, false, '', '' ),
			'variation default'   => array( GoogleCategory::class, true, '', '' ),
			'simple custom'       => array( GoogleCategory::class, false, ' custom ', 'custom' ),
			'variation custom'    => array( GoogleCategory::class, true, ' custom ', 'custom' ),
			'simple stored'       => array( GoogleCategory::class, false, '499954', '499954', '499954' ),
			'variation stored'    => array( GoogleCategory::class, true, '499954', '499954', '499954' ),
			'simple built-in'     => array( GoogleCategoryWithValueOptions::class, false, ' 1 ', '1', '1', false ),
			'variation built-in'  => array( GoogleCategoryWithValueOptions::class, true, ' 1 ', '1', '1', false ),
		);
	}

	/**
	 * @dataProvider submitted_values
	 * @param array $submitted Submitted fields.
	 * @param array $expected Accepted form data.
	 */
	public function test_submitted_values( $submitted, $expected ) {
		$form = new AttributesForm( array( Condition::class, GoogleCategory::class ) );
		$form->set_name( 'attributes' );
		$form->submit( $submitted );

		$this->assertSame( $expected, $form->get_data() );
		$this->assertTrue( $form->is_submitted() );
		$this->assertSame( 'pinterest_attributes', $form->get_view_data()['name'] );
		$fields = $form->get_view_data()['children'];
		$this->assertSame( 'pinterest_attributes[condition]', $fields['condition']['name'] );
		$this->assertSame( 'pinterest_attributes[google_product_category]', $fields['google_product_category']['name'] );
		$this->assertSame( array( '', 'new', 'refurbished', 'used' ), array_keys( $fields['condition']['options'] ) );
	}

	/**
	 * Submitted field cases.
	 *
	 * @return array
	 */
	public function submitted_values() {
		return array(
			'missing fields'                     => array( array(), array() ),
			'empty fields'                       => array(
				array(
					'condition'               => '',
					'google_product_category' => '',
				),
				array(
					'condition'               => '',
					'google_product_category' => '',
				),
			),
			'trimmed values and unrelated input' => array(
				array(
					'condition'               => ' used ',
					'google_product_category' => ' 2271 ',
					'unrelated'               => 'ignore',
				),
				array(
					'condition'               => 'used',
					'google_product_category' => '2271',
				),
			),
		);
	}
}
