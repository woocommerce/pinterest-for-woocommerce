<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Feed;

use ReflectionClass;
use ReflectionMethod;
use \WC_Helper_Product;
use \WC_Unit_Test_Case;
use \WC_Product_Variable;

use Automattic\WooCommerce\Pinterest\Logger;
use Automattic\WooCommerce\Pinterest\ProductsXmlFeed;
use Automattic\WooCommerce\Pinterest\Product\GoogleCategorySearch;
use Automattic\WooCommerce\Pinterest\Product\GoogleProductTaxonomy;
use Automattic\WooCommerce\Pinterest\Product\Attributes\AttributeManager;
use Automattic\WooCommerce\Pinterest\Product\Attributes\Condition;
use Automattic\WooCommerce\Pinterest\Product\Attributes\GoogleCategory;

/**
 * Feed file generation testing class.
 */
class Pinterest_Test_Feed extends WC_Unit_Test_Case {

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
	 * This is more like an integration test than a UT.
	 * It checks the general execution of the XML generation for a hypothetical feed with just one product.
	 * All individual functions are tested separately this just roughly tests the structure.
	 *
	 * @group feed
	 */
	public function testSimpleProductXmlItem() {
		$product  = WC_Helper_Product::create_simple_product();

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
		$this->assertArrayNotHasKey( 'item_group_id', $children, 'Simple products should not have the item_group_id set.' );

		// From WC_Helper_Product.
		$this->assertEquals( 'Dummy Product', $children['title'] );

		// No description set.
		$this->assertArrayNotHasKey( 'description', $children, 'Description not set, the key should not be set.' );

		// Product type not set.
		$this->assertEquals( 'Uncategorized', $g_children['product_type'] );

		// This should be the permalink.
		$this->assertEquals( 'http://example.org/?product=dummy-product', $children['link'] );

		// No description set.
		$this->assertArrayNotHasKey( 'image_link', $g_children, 'By default product does not have an image link.' );

		// Default availability from WC_Helper_Product.
		$this->assertEquals( 'in stock', $g_children['availability'] );

		// Default price from WC_Helper_Product.
		$this->assertEquals( '10.00USD', $g_children['price'] );

		// No description set.
		$this->assertArrayNotHasKey( 'image_link', $g_children, "By default product does not have an image link." );

		// No sale price set.
		$this->assertArrayNotHasKey( 'sale_price', $children, "By default product does not have a sale price." );

		// Dummy SKU from WC_Helper_Product
		$this->assertEquals( 'DUMMY SKU', $g_children['mpn'] );

		// We don't support tax collumn yet.
		$this->assertArrayNotHasKey( 'tax', $g_children, 'When tax becomes supported this test should be updated.' );

		// We don't support shipping collumn yet.
		$this->assertArrayNotHasKey( 'shipping', $g_children, 'When shipping becomes supported this test should be updated.' );

		// g:additional_image_link.
		$this->assertArrayNotHasKey( 'additional_image_link', $g_children, 'By default we don\'t have additional image links.' );

		// Condition is not set by default.
		$this->assertArrayNotHasKey( 'condition', $g_children, 'By default we don\'t have the condition set.' );
	}

	/**
	 * Test if a product with price set to 0 is skipped from the feed
	 *
	 * @group feed
	 */
	public function testSkipZeroPriceProductXML() {
		// Create product with zero price
		$product = WC_Helper_Product::create_simple_product(
			true,
			array(
				'regular_price' => 0,
			)
		);

		$xml = ProductsXmlFeed::get_xml_item( $product );
		$this->assertEquals( '', $xml );
	}

	/**
	 * @group feed
	 */
	public function testDescriptionForSimpleProductXML() {
		$description_method = $this->getProductsXmlFeedAttributeMethod( 'description' );

		// No description set.
		$product  = WC_Helper_Product::create_simple_product();
		$xml      = $description_method( $product );
		$this->assertEquals( '', $xml );

		$desc = 'Test description.';
		// Product with description
		$product_with_description = WC_Helper_Product::create_simple_product(
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
		$product            = new WC_Product_Variable();
		$variation_product  = WC_Helper_Product::create_variation_product( $product );
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
		$product    = WC_Helper_Product::create_simple_product();
		$product_id = $product->get_id();
		$xml        = $id_method( $product );
		$this->assertEquals( "<g:id>{$product_id}</g:id>", $xml );
	}

	/**
	 * @group feed
	 */
	public function testPropertyIdSimpleProductXML() {
		$id_method = $this->getProductsXmlFeedAttributeMethod( 'item_group_id' );
		$product   = WC_Helper_Product::create_simple_product();
		$xml       = $id_method( $product );
		// Simple products have no parents so they don't have group id.
		$this->assertEquals( '', $xml );
	}

	/**
	 * @group feed
	 */
	public function testPropertyIdVariableProductXML() {
		$group_id_method   = $this->getProductsXmlFeedAttributeMethod( 'item_group_id' );
		$product           = new WC_Product_Variable();
		$variation_product = WC_Helper_Product::create_variation_product( $product );
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
		$product      = WC_Helper_Product::create_simple_product();
		$xml          = $title_method( $product );
		// create_simple_product gives the product `Dummy Product` title.
		$this->assertEquals( '<title><![CDATA[Dummy Product]]></title>', $xml );
	}

	/**
	 * @group feed
	 */
	public function testStripHtmlTagsPropertyTitleXML() {
		$title_method = $this->getProductsXmlFeedAttributeMethod( 'title' );
		$product      = WC_Helper_Product::create_simple_product(
			true,
			array(
				'name' => 'Dummy Product <h1>Dummy Tag</h1>',
			)
		);
		$xml          = $title_method( $product );
		$this->assertEquals( '<title><![CDATA[Dummy Product Dummy Tag]]></title>', $xml );
	}

	/**
	 * @group feed
	 */
	public function testStripHtmlTagsPropertyDescriptionXML() {
		$description_method = $this->getProductsXmlFeedAttributeMethod( 'description' );
		$product            = WC_Helper_Product::create_simple_product(
			true,
			array(
				'short_description' => 'Dummy Description <h1>Dummy Tag</h1>',
			)
		);
		$xml                = $description_method( $product );
		$this->assertEquals( '<description><![CDATA[Dummy Description Dummy Tag]]></description>', $xml );
	}

	/**
	 * @group feed
	 */
	public function testStripShortcodesPropertyDescriptionXML() {
		$description_method = $this->getProductsXmlFeedAttributeMethod( 'description' );

		// Add simple shortcode to test.
		add_shortcode(
			'pinterest_for_woocommerce_sample_test_shortcode',
			function () {
				return 'sample-shortcode-rendered-result';
			}
		);

		$description          = 'This product has a shortcode [pinterest_for_woocommerce_sample_test_shortcode] that will get stripped out.';
		$expected_description = 'This product has a shortcode  that will get stripped out.';

		$product = WC_Helper_Product::create_simple_product(
			true,
			array(
				'short_description' => $description,
			)
		);

		$xml = $description_method( $product );

		$this->assertEquals( "<description><![CDATA[{$expected_description}]]></description>", $xml );
	}

	/**
	 * @group feed
	 */
	public function testNoStripShortcodesPropertyDescriptionXML() {
		$description_method = $this->getProductsXmlFeedAttributeMethod( 'description' );

		// Add simple shortcode to test.
		add_shortcode(
			'pinterest_for_woocommerce_sample_test_shortcode',
			function () {
				return 'sample-shortcode-rendered-result';
			}
		);

		// Add filter to apply shortcodes on description.
		add_filter(
			'pinterest_for_woocommerce_product_description_apply_shortcodes',
			function() {
				return true;
			}
		);

		$description          = 'This product has a shortcode [pinterest_for_woocommerce_sample_test_shortcode] that will not get stripped out.';
		$expected_description = 'This product has a shortcode sample-shortcode-rendered-result that will not get stripped out.';

		$product = WC_Helper_Product::create_simple_product(
			true,
			array(
				'short_description' => $description,
			)
		);

		$xml = $description_method( $product );

		$this->assertEquals( "<description><![CDATA[{$expected_description}]]></description>", $xml );
	}

	/**
	 * @group feed
	 *
	 * @return void
	 */
	public function testDescriptionClipping() {
		$description_method = $this->getProductsXmlFeedAttributeMethod( 'description' );
		/**
		 * Mock logger object that will catch any logged messages.
		 */
		$mock_logger = new class {

			static $message = '';
			public function log( $level, $msg )
			{
				self::$message = $msg;
			}
		};

		Logger::$logger = $mock_logger;

		/**
		 * Generate a description string too big for the feed.
		 * The limit is 10K so we generate 1010 char length string.
		 */
		$description          = str_repeat( 'abcdefghij', 1000 + 1 );
		$expected_description = str_repeat( 'abcdefghij', 1000 );

		$product = WC_Helper_Product::create_simple_product(
			true,
			array(
				'short_description' => $description,
			)
		);

		$xml = $description_method( $product );

		$this->assertEquals( "<description><![CDATA[{$expected_description}]]></description>", $xml );

		// Information about size limit exceeded has been logged.
		$this->assertEquals( "The product [{$product->get_id()}] has a description longer than the allowed limit.", $mock_logger::$message );
	}

	/**
	 * @group feed
	 */
	public function testPropertyProductTypeXML() {
		$product_type_method = $this->getProductsXmlFeedAttributeMethod( 'g:product_type' );
		$product             = WC_Helper_Product::create_simple_product();
		$xml                 = $product_type_method( $product );
		// create_simple_product gives the product 'Uncategorized' type.
		$this->assertEquals( '<g:product_type>Uncategorized</g:product_type>', $xml );
	}

	/**
	 * @group feed
	 */
	public function testPropertyProductTypeVariableProductXML() {
		$product_type_method  = $this->getProductsXmlFeedAttributeMethod( 'g:product_type' );
		$product              = new WC_Product_Variable();
		$variation_product    = WC_Helper_Product::create_variation_product( $product );
		$variation_product_id = $variation_product->get_children()[0];
		$variation_product    = wc_get_product( $variation_product_id );
		$xml                  = $product_type_method( $variation_product );
		// create_simple_product gives the product 'Uncategorized' type.
		$this->assertEquals( '<g:product_type>Uncategorized</g:product_type>', $xml );
	}

	/**
	 * @group feed
	 */
	public function testPropertyLinkXML() {
		$link_method = $this->getProductsXmlFeedAttributeMethod( 'link' );
		$product     = WC_Helper_Product::create_simple_product();
		$xml         = $link_method( $product );
		// create_simple_product gives the product 'Uncategorized' type.
		$this->assertEquals( '<link><![CDATA[http://example.org/?product=dummy-product]]></link>', $xml );
	}

	/**
	 * @group feed
	 */
	public function testPropertyImageLinkXML() {
		$image_link_method = $this->getProductsXmlFeedAttributeMethod( 'g:image_link' );
		$product           = WC_Helper_Product::create_simple_product();

		$xml = $image_link_method( $product );
		// By default no image link is set.
		$this->assertEquals( '', $xml );

		// Add dummy image entry.
		$attachment = array(
			'post_mime_type' => 'image/png',
			'post_title'     => 'product image',
		);
		$attachment_id = wp_insert_attachment( $attachment, 'product_image.png', $product->get_id() );

		// Add attachment id as product image id.
		$product->set_image_id( $attachment_id );
		$product->save();

		$xml = $image_link_method( $product );
		$this->assertEquals( '<g:image_link><![CDATA[http://example.org/wp-content/uploads/product_image.png]]></g:image_link>', $xml );
	}

	/**
	 * @group feed
	 */
	public function testPropertyAvailabiltiyXML() {
		$availability_method = $this->getProductsXmlFeedAttributeMethod( 'g:availability' );
		$product             = WC_Helper_Product::create_simple_product();

		// Set different statuses and test.
		$product->set_stock_status( 'instock' );
		$xml = $availability_method( $product );
		$this->assertEquals( '<g:availability>in stock</g:availability>', $xml );

		$product->set_stock_status( 'outofstock' );
		$xml = $availability_method( $product );
		// create_simple_product gives the product 'Uncategorized' type.
		$this->assertEquals( '<g:availability>out of stock</g:availability>', $xml );

		$product->set_stock_status( 'onbackorder' );
		$xml = $availability_method( $product );
		// create_simple_product gives the product 'Uncategorized' type.
		$this->assertEquals( '<g:availability>preorder</g:availability>', $xml );
	}

	/**
	 * @group feed
	 */
	public function testPropertyPriceXML() {
		$price_method = $this->getProductsXmlFeedAttributeMethod( 'g:price' );
		$product      = WC_Helper_Product::create_simple_product( true, array( "regular_price" => 15 ) );
		$xml          = $price_method( $product );
		$this->assertEquals( '<g:price>15.00USD</g:price>', $xml );

		// Test with another currency.
		$old_currency = get_woocommerce_currency();
		update_option( 'woocommerce_currency', 'JPY' );
		$price_method = $this->getProductsXmlFeedAttributeMethod( 'g:price' );
		$product      = WC_Helper_Product::create_simple_product( true, array( "regular_price" => 15 ) );
		$xml          = $price_method( $product );
		$this->assertEquals( '<g:price>15JPY</g:price>', $xml );

		// Update again the currency to the old currency.
		update_option( 'woocommerce_currency', $old_currency );
	}

	/**
	 * @group feed
	 */
	public function testPropertySalePriceXML() {
		$sale_price_method = $this->getProductsXmlFeedAttributeMethod( 'sale_price' );

		// No sale price is set.
		$product = WC_Helper_Product::create_simple_product( true, array( "regular_price" => 15 ) );
		$xml     = $sale_price_method( $product );
		$this->assertEquals( '', $xml );

		// Sale price set.
		$product = WC_Helper_Product::create_simple_product(
			true,
			array(
				'regular_price' => 15,
				'sale_price'    => 5,
			)
		);
		$xml     = $sale_price_method( $product );
		$this->assertEquals( '<sale_price>5.00USD</sale_price>', $xml );
	}

	/**
	 * @group feed
	 */
	public function testPropertyPriceVariableProductXML() {
		$price_method      = $this->getProductsXmlFeedAttributeMethod( 'g:price' );
		$product           = new WC_Product_Variable();
		$variation_product = WC_Helper_Product::create_variation_product( $product );
		/*
		 * In UT flow we need to fetch the product again from the DB after creation.
		 * This ensures correct initialization of visible variations.
		 * Without that the variable price methods think that we don't have visible children.
		 * Quirk of create_variation_product.
		 */
		$product = wc_get_product( $variation_product->get_id() );
		$xml     = $price_method( $product );
		// 10.00USD is the cheapest variation created by create_variation_product
		$this->assertEquals( '<g:price>10.00USD</g:price>', $xml );
	}

	/**
	 * @group feed
	 */
	public function testPropertyMpnXML() {
		$mpn_method = $this->getProductsXmlFeedAttributeMethod( 'g:mpn' );
		$product    = WC_Helper_Product::create_simple_product();
		$xml        = $mpn_method( $product );
		$this->assertEquals( '<g:mpn>DUMMY SKU</g:mpn>', $xml );
	}


	/**
	 * @group feed
	 */
	public function testEscapeSpecialCharsInSKUForMpnXML() {
		$mpn_method = $this->getProductsXmlFeedAttributeMethod( 'g:mpn' );
		$product    = WC_Helper_Product::create_simple_product(
			true,
			array(
				'sku' => "invalid&sku"
			)
		 );
		$xml        = $mpn_method( $product );
		$this->assertEquals( '<g:mpn>invalid&amp;sku</g:mpn>', $xml );
	}

	/**
	 * @group feed
	 */
	public function testPropertyAdditionalImageLinkXML() {
		$additional_image_link_method = $this->getProductsXmlFeedAttributeMethod( 'g:additional_image_link' );
		$product                      = WC_Helper_Product::create_simple_product();

		$xml = $additional_image_link_method( $product );
		// By default no galery images are set.
		$this->assertEquals( '', $xml );

		// Add dummy image entry.
		$attachment = array(
			'post_mime_type' => 'image/png',
			'post_title'     => 'product image 1',
		);
		$attachment_id_1 = wp_insert_attachment( $attachment, 'product_image_1.png', $product->get_id() );
		// Product needs main image to use gallery so lets set this up here.
		$product->set_image_id( $attachment_id_1 );

		// Add second dummy image entry.
		$attachment_id_2 = array(
			'post_mime_type' => 'image/png',
			'post_title'     => 'product image 2',
		);
		$attachment_id_2 = wp_insert_attachment( $attachment, 'product_image_2.png', $product->get_id() );

		// Add attachment id as product image id.
		$product->set_gallery_image_ids( [ $attachment_id_1, $attachment_id_2 ] );
		$product->save();

		$xml = $additional_image_link_method( $product );
		$this->assertEquals( '<g:additional_image_link><![CDATA[http://example.org/wp-content/uploads/product_image_1.png,http://example.org/wp-content/uploads/product_image_2.png]]></g:additional_image_link>', $xml );
	}

	/**
	 * @group feed
	 */
	public function testAttributesConditionXML() {
		$method = ( new ReflectionClass( ProductsXmlFeed::class ) )->getMethod( 'get_attributes_xml' );
		$method->setAccessible( true );

		$product = WC_Helper_Product::create_simple_product();
		$xml = $method->invoke( null, $product, '' );
		// No attributes set, output should be empty.
		$this->assertEquals( '', $xml );

		$condition         = new Condition( 'new' );
		$attribute_manager = AttributeManager::instance();
		$attribute_manager->update( $product, $condition );

		$xml = $method->invoke( null, $product, '' );
		// Condition attribute was set, we should see it in the output.
		$this->assertEquals( '<g:condition>new</g:condition>' . PHP_EOL, $xml );
	}

	/**
	 * @group feed
	 *
	 * Since GoogleCategory is not validated we would end up with yet another test like testAttributesConditionXML.
	 * To make this a bit more useful this will be more of an integration test.
	 */
	public function testAttributesGoogleCategoryXML() {
		$method = ( new ReflectionClass( ProductsXmlFeed::class ) )->getMethod( 'get_attributes_xml' );
		$method->setAccessible( true );

		$product = WC_Helper_Product::create_simple_product();
		$xml = $method->invoke( null, $product, '' );
		// No attributes set, output should be empty.
		$this->assertEquals( '', $xml );

		$full_category_name_method = new ReflectionMethod( GoogleCategorySearch::class, 'full_category_name' );
		$full_category_name_method->setAccessible( true );
		$taxonomy                  = GoogleProductTaxonomy::TAXONOMY[502979]; // Randomly selected category - i just made sure that it has parent.
		$full_taxonomy_name        = $full_category_name_method->invoke( new GoogleCategorySearch(), $taxonomy );
		$condition                 = new GoogleCategory( $full_taxonomy_name );
		$attribute_manager         = AttributeManager::instance();
		$attribute_manager->update( $product, $condition );

		$xml = $method->invoke( null, $product, '' );
		// Google product category attribute was set, we should see it in the output.
		$this->assertEquals( '<g:google_product_category>Arts &amp; Entertainment &gt; Hobbies &amp; Creative Arts &gt; Arts &amp; Crafts &gt; Art &amp; Craft Kits &gt; Jewelry Making Kits</g:google_product_category>' . PHP_EOL, $xml );
	}

	/**
	 * Helper function for extracting the static private members of the ProductsXmlFeed class.
	 * Gets the property method then just pass the product and voila.
	 *
	 * @param string $attribute
	 * @return function
	 */
	private function getProductsXmlFeedAttributeMethod( $attribute ) {
		$method_name = 'get_property_' . str_replace( ':', '_', $attribute );
		$class       = new ReflectionClass( ProductsXmlFeed::class );
		$method      = $class->getMethod( $method_name );
		$method->setAccessible( true );

		return function( $product ) use ( $method, $attribute ) {
			return $method->invoke( null, $product, $attribute );
		};
	}

	/**
	 * Remove filters and shortcodes.
	 */
	public function tearDown() {
		parent::tearDown();

		// Remove any added filter.
		remove_all_filters( 'pinterest_for_woocommerce_product_description_apply_shortcodes' );

		// Remove added shortcodes.
		remove_shortcode( 'pinterest_for_woocommerce_sample_test_shortcode' );

		// Reset logger.
		Logger::$logger = null;
	}

}

