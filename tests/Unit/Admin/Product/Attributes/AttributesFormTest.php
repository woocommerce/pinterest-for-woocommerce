<?php
/**
 * Attribute form values at the submission boundary.
 *
 * @package Automattic\WooCommerce\Pinterest\Tests
 */

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Admin\Product\Attributes;

use Automattic\WooCommerce\Pinterest\Admin\Product\Attributes\AttributesForm;
use Automattic\WooCommerce\Pinterest\Product\Attributes\Condition;
use Automattic\WooCommerce\Pinterest\Product\Attributes\GoogleCategory;
use WP_UnitTestCase;

/**
 * Verify the existing form's names and submitted values.
 */
class AttributesFormTest extends WP_UnitTestCase {
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
