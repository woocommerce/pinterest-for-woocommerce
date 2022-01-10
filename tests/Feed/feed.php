<?php

namespace Automattic\WooCommerce\Pinterest;

class Pinterest_Test_Feed extends \WC_Unit_Test_Case {
	/**
	 * @group feed
	 */
	public function testHeader() {
		$actual_header = ProductsXmlFeed::get_xml_header();
		$this->assertEquals(
		"<?xml version=\"1.0\"?>
<rss version=\"2.0\" xmlns:g=\"http://base.google.com/ns/1.0\">
	<channel>
",
		$actual_header
		);
	}

	/**
	 * @group feed
	 */
	public function testFooter() {
		$actual_footer = ProductsXmlFeed::get_xml_footer();
		$this->assertEquals(
		"	</channel>
</rss>",
		$actual_footer
		);
	}

	/**
	 * @group feed
	 */
	public function testSimpleProductXmlItem() {
		$product  = \WC_Helper_Product::create_simple_product();

		// We need header and footer so we can process XML directly.
		$xml      = ProductsXmlFeed::get_xml_header();
		$xml     .= ProductsXmlFeed::get_xml_item( $product );
		$xml     .= ProductsXmlFeed::get_xml_footer();

		$simplex_object = simplexml_load_string( $xml, "SimpleXMLElement", LIBXML_NOCDATA );
		$children       = (array) $simplex_object->channel->item->children();
		$g_children     = (array) $simplex_object->channel->item->children( "g", true ); // Child nodes that are prefixed.

		// Id value 0 comes from WC_Helper_Product.
		$this->assertEquals( $product->get_id(), $g_children['id']);

		// Not a variation so no item group id.
		$this->assertArrayNotHasKey( "item_group_id", $children, "Simple products should not have the item_group_id set." );

		// From WC_Helper_Product.
		$this->assertEquals( "Dummy Product", $children['title'] );

		// No description set.
		$this->assertArrayNotHasKey( "description", $children, "Description not set, the key should not be set." );

		// Product type not set.
		$this->assertEquals( "Uncategorized", $g_children['product_type'] );

		// This should be the permalink.
		$this->assertEquals( "http://example.org/?product=dummy-product", $children['link'] );

		// No description set.
		$this->assertArrayNotHasKey( "image_link", $g_children, "By default product does not have an image link." );

		// Default availability from WC_Helper_Product.
		$this->assertEquals( "in stock", $g_children['availability'] );

		// Default price from WC_Helper_Product.
		$this->assertEquals( "10USD", $g_children['price'] );

		// No description set.
		$this->assertArrayNotHasKey( "image_link", $g_children, "By default product does not have an image link." );

		// No sale price set.
		$this->assertArrayNotHasKey( "sale_price", $children, "By default product does not have a sale price." );

		// Dummy SKU from WC_Helper_Product
		$this->assertEquals( "DUMMY SKU", $g_children['mpn'] );

		// We don't support tax collumn yet.
		$this->assertArrayNotHasKey( "tax", $g_children, "When tax becomes supported this test should be updated." );

		// We don't support shipping collumn yet.
		$this->assertArrayNotHasKey( "shipping", $g_children, "When shipping becomes supported this test should be updated." );

		// g:additional_image_link.
		$this->assertArrayNotHasKey( "additional_image_link", $g_children, "By default we don't have additional image links." );

	}

	/**
	 * @group feed
	 */
	public function testDescriptionForSimpleProductXML() {
		$description_method = $this->getProductsXmlFeedAttributeMethod( 'description' );

		// No description set.
		$product  = \WC_Helper_Product::create_simple_product();
		$xml      = $description_method( $product );
		$this->assertEquals( '', $xml );

		$desc = 'Test description.';
		// Product with description
		$product_with_description = \WC_Helper_Product::create_simple_product(
			true,
			array(
				'short_description' => $desc
			)
		);
		$xml = $description_method( $product_with_description );
		$this->assertEquals( "<description><![CDATA[{$desc}]]></description>", $xml );
	}

	/**
	 * @group feed
	 */
	public function testDescriptionForVariableProductXML() {
		$description_method = $this->getProductsXmlFeedAttributeMethod( 'description' );

		// By passing manually created Variable Product the create_variation_product will add children to it.
		$product            = new \WC_Product_Variable();
		$variation_product  = \WC_Helper_Product::create_variation_product( $product );
		// create_variation_product creates multiple children, picking up the first one
		$child_id           = $variation_product->get_children()[0];
		$child_product      = wc_get_product( $child_id );
		$xml                = $description_method( $child_product );

		/*
		 * With no description set the code will use the excerpt.
		 * The excerpt for the product variation is build from the attributes summary.
		 */
		$attributes_summary = $child_product->get_attribute_summary( 'edit' );
		$this->assertEquals( "<description><![CDATA[{$attributes_summary}]]></description>", $xml );

		// Get the next variable product for tests with description set.
		$child_id      = $variation_product->get_children()[1];
		$child_product = wc_get_product( $child_id );
		$desc = 'Test description.';
		$child_product->set_description( $desc );
		$child_product->save();
		$xml = $description_method( $child_product );
		$this->assertEquals( "<description><![CDATA[{$desc}]]></description>", $xml );
	}

	/**
	 * @group feed
	 */
	public function testProductIdXML() {
		$id_method  = $this->getProductsXmlFeedAttributeMethod( 'g:id' );
		$product    = \WC_Helper_Product::create_simple_product();
		$product_id = $product->get_id();
		$xml        = $id_method( $product );
		$this->assertEquals( "<g:id>{$product_id}</g:id>", $xml );
	}

	/**
	 * @group feed
	 */
	public function testPropertyIdSimpleProductXML() {
		$id_method = $this->getProductsXmlFeedAttributeMethod( 'item_group_id' );
		$product   = \WC_Helper_Product::create_simple_product();
		$xml       = $id_method( $product );
		// Simple products have no parents so they don't have group id.
		$this->assertEquals( "", $xml );
	}

	/**
	 * @group feed
	 */
	public function testPropertyIdVariableProductXML() {
		$group_id_method   = $this->getProductsXmlFeedAttributeMethod( 'item_group_id' );
		$product           = new \WC_Product_Variable();
		$variation_product = \WC_Helper_Product::create_variation_product( $product );
		$child_product_id  = $variation_product->get_children()[0];
		$child_product     = wc_get_product( $child_product_id );

		$parent_product_id = $product->get_id();
		$xml               = $group_id_method( $child_product );
		// Item group id should be the parent product id.
		$this->assertEquals( "<item_group_id>{$parent_product_id}</item_group_id>", $xml );
	}

	/**
	 * @group feed
	 */
	public function testPropertyTitleXML() {
		$title_method = $this->getProductsXmlFeedAttributeMethod( 'title' );
		$product      = \WC_Helper_Product::create_simple_product();
		$xml          = $title_method( $product );
		// create_simple_product gives the product `Dummy Product` title.
		$this->assertEquals( "<title><![CDATA[Dummy Product]]></title>", $xml );
	}

	/**
	 * @group feed
	 */
	public function testPropertyProductTypeXML() {
		$product_type_method = $this->getProductsXmlFeedAttributeMethod( 'g:product_type' );
		$product             = \WC_Helper_Product::create_simple_product();
		$xml                 = $product_type_method( $product );
		// create_simple_product gives the product 'Uncategorized' type.
		$this->assertEquals( "<g:product_type>Uncategorized</g:product_type>", $xml );
	}

	/**
	 * @group feed
	 */
	public function testPropertyLinkXML() {
		$link_method = $this->getProductsXmlFeedAttributeMethod( 'link' );
		$product     = \WC_Helper_Product::create_simple_product();
		$xml         = $link_method( $product );
		// create_simple_product gives the product 'Uncategorized' type.
		$this->assertEquals( "<link><![CDATA[http://example.org/?product=dummy-product]]></link>", $xml );
	}

	/**
	 * @group feed
	 */
	public function testPropertyImageLinkXML() {
		$image_link_method = $this->getProductsXmlFeedAttributeMethod( 'g:image_link' );
		$product           = \WC_Helper_Product::create_simple_product();

		$xml = $image_link_method( $product );
		// By default no image link is set.
		$this->assertEquals( "", $xml );

		// Add dummy image entry.
		$attachment = array(
			'post_mime_type' => 'image/png'
		,   'post_title'     => 'product image'
		);
		$attachment_id = wp_insert_attachment( $attachment, 'product_image.png', $product->get_id() );

		// Add attachment id as product image id.
		$product->set_image_id( $attachment_id );
		$product->save();

		$xml = $image_link_method( $product );
		$this->assertEquals( "<g:image_link><![CDATA[http://example.org/wp-content/uploads/product_image.png]]></g:image_link>", $xml );
	}

	/**
	 * Gets the property method. Just pass the product and voila.
	 *
	 * @param string $attribute
	 * @return function
	 */
	public function getProductsXmlFeedAttributeMethod( $attribute ) {
		$method_name = 'get_property_' . str_replace( ':', '_', $attribute );
		$class       = new \ReflectionClass('Automattic\WooCommerce\Pinterest\ProductsXmlFeed');
		$method      = $class->getMethod( $method_name );
		$method->setAccessible(true);

		return function( $product ) use ( $method, $attribute ) {
			return $method->invoke( null, $product, $attribute );
		};
	}

}

