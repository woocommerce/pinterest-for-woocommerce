<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Feed;

use Automattic\WooCommerce\Pinterest\LocalFeedConfigs;
use Automattic\WooCommerce\Pinterest\Logger;
use Automattic\WooCommerce\Pinterest\Product\Attributes\AttributeManager;
use Automattic\WooCommerce\Pinterest\Product\Attributes\Condition;
use Automattic\WooCommerce\Pinterest\Product\Attributes\GoogleCategory;
use Automattic\WooCommerce\Pinterest\Product\GoogleCategorySearch;
use Automattic\WooCommerce\Pinterest\Product\GoogleProductTaxonomy;
use Automattic\WooCommerce\Pinterest\ProductsXmlFeed;
use ReflectionClass;
use ReflectionMethod;
use ShippingHelpers;
use WC_Helper_Product;
use WC_Product_Variable;
use WC_Unit_Test_Case;

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
			'<?xml version="1.0"?>
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
	<channel>
',
			$actual_header
		);
	}

	/**
	 * @group feed
	 */
	public function testFooter() {
		$actual_footer = ProductsXmlFeed::get_xml_footer();
		$this->assertEquals(
			'	</channel>
</rss>',
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
		$product = WC_Helper_Product::create_simple_product(
			true,
			array(
				'sku' => 'DUMMY SKU',
			)
		);

		// We need header and footer so we can process XML directly.
		$xml  = ProductsXmlFeed::get_xml_header();
		$xml .= ProductsXmlFeed::get_xml_item( $product, 'US' );
		$xml .= ProductsXmlFeed::get_xml_footer();

		$simplex_object = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NOCDATA );
		$children       = (array) $simplex_object->channel->item->children();
		$g_children     = (array) $simplex_object->channel->item->children( 'g', true ); // Child nodes that are prefixed.

		// Id value 0 comes from WC_Helper_Product.
		$this->assertEquals( $product->get_id(), $g_children['id'] );

		// Not a variation so no item group id.
		$this->assertArrayNotHasKey( 'item_group_id', $children, 'Simple products should not have the item_group_id set.' );

		// From WC_Helper_Product.
		$this->assertEquals( 'Dummy Product', $children['title'] );

		// No description set.
		$this->assertArrayNotHasKey( 'description', $children, 'Description not set, the key should not be set.' );

		// Product type not set.
		$this->assertEquals( 'Uncategorized', $g_children['product_type'] );

		// This should be the permalink, with the Pinterest UTM contract appended.
		$this->assertEquals( $this->getProductPermalinkWithUtm( $product ), $children['link'] );
		$this->assertStringContainsString( 'utm_source=pinterest&utm_medium=social', $children['link'] );

		// No description set.
		$this->assertArrayNotHasKey( 'image_link', $g_children, 'By default product does not have an image link.' );

		// Default availability from WC_Helper_Product.
		$this->assertEquals( 'in stock', $g_children['availability'] );

		// Default price from WC_Helper_Product.
		$this->assertEquals( '10.00USD', $g_children['price'] );

		// No description set.
		$this->assertArrayNotHasKey( 'image_link', $g_children, 'By default product does not have an image link.' );

		// No sale price set.
		$this->assertArrayNotHasKey( 'sale_price', $children, 'By default product does not have a sale price.' );

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

		$xml = ProductsXmlFeed::get_xml_item( $product, 'US' );
		$this->assertEquals( '', $xml );
	}

	/**
	 * @group feed
	 */
	public function testDescriptionForSimpleProductXML() {
		$description_method = $this->getProductsXmlFeedAttributeMethod( 'description' );

		// No description set.
		$product = WC_Helper_Product::create_simple_product();
		$xml     = $description_method( $product );
		$this->assertEquals( '', $xml );

		$desc = 'Test description.';
		// Product with description
		$product_with_description = WC_Helper_Product::create_simple_product(
			true,
			array(
				'short_description' => $desc,
			)
		);
		$xml                      = $description_method( $product_with_description );
		$this->assertEquals( "<description><![CDATA[{$desc}]]></description>", $xml );
	}

	/**
	 * @group feed
	 */
	public function testDescriptionForVariableProductXML() {
		$description_method = $this->getProductsXmlFeedAttributeMethod( 'description' );

		// By passing manually created Variable Product the create_variation_product will add children to it.
		$product           = new WC_Product_Variable();
		$variation_product = WC_Helper_Product::create_variation_product( $product );

		// Give the parent a short description so variations can fall back to it.
		$parent_short_desc = 'Parent short description.';
		$variation_product->set_short_description( $parent_short_desc );
		$variation_product->save();

		// create_variation_product creates multiple children, picking up the first one
		$child_id      = $variation_product->get_children()[0];
		$child_product = wc_get_product( $child_id );
		$xml           = $description_method( $child_product );

		/*
		 * With no variation description set the feed should fall back to the parent's
		 * short description, not the attribute summary stored in the variation excerpt.
		 */
		$this->assertEquals( "<description><![CDATA[{$parent_short_desc}]]></description>", $xml );

		// When the parent has no short description, fall back to the parent's long description.
		$parent_long_desc = 'Parent long description.';
		$variation_product->set_short_description( '' );
		$variation_product->set_description( $parent_long_desc );
		$variation_product->save();
		$xml = $description_method( $child_product );
		$this->assertEquals( "<description><![CDATA[{$parent_long_desc}]]></description>", $xml );

		// Get the next variable product child for tests with the variation description set directly.
		$child_id      = $variation_product->get_children()[1];
		$child_product = wc_get_product( $child_id );
		$desc          = 'Test description.';
		$child_product->set_description( $desc );
		$child_product->save();
		$xml = $description_method( $child_product );
		$this->assertEquals( "<description><![CDATA[{$desc}]]></description>", $xml );
	}

	/**
	 * When the parent has both short and long descriptions, variations without
	 * their own description should fall back to the parent's short description
	 * (not the long one) per the documented fallback order.
	 *
	 * @group feed
	 */
	public function testDescriptionVariationPrefersParentShortOverLong() {
		$description_method = $this->getProductsXmlFeedAttributeMethod( 'description' );

		$product           = new WC_Product_Variable();
		$variation_product = WC_Helper_Product::create_variation_product( $product );

		$parent_short_desc = 'Parent short description.';
		$parent_long_desc  = 'Parent long description.';
		$variation_product->set_short_description( $parent_short_desc );
		$variation_product->set_description( $parent_long_desc );
		$variation_product->save();

		$child_id      = $variation_product->get_children()[0];
		$child_product = wc_get_product( $child_id );

		// Pin precondition: the variation must not have its own description,
		// otherwise this test would pass for the wrong reason if WC ever
		// auto-inherits the parent description on variations.
		$this->assertSame( '', $child_product->get_description() );

		$xml = $description_method( $child_product );

		// Short description wins over long description.
		$this->assertEquals( "<description><![CDATA[{$parent_short_desc}]]></description>", $xml );
	}

	/**
	 * When the variation has its own description, it should take precedence
	 * over both the parent short and long descriptions.
	 *
	 * @group feed
	 */
	public function testDescriptionVariationOwnDescriptionWinsOverParent() {
		$description_method = $this->getProductsXmlFeedAttributeMethod( 'description' );

		$product           = new WC_Product_Variable();
		$variation_product = WC_Helper_Product::create_variation_product( $product );

		$variation_product->set_short_description( 'Parent short description.' );
		$variation_product->set_description( 'Parent long description.' );
		$variation_product->save();

		$child_id      = $variation_product->get_children()[0];
		$child_product = wc_get_product( $child_id );

		$own_desc = 'Variation specific description.';
		$child_product->set_description( $own_desc );
		$child_product->save();

		$xml = $description_method( $child_product );
		$this->assertEquals( "<description><![CDATA[{$own_desc}]]></description>", $xml );
	}

	/**
	 * When neither the variation nor its parent provide a description, the
	 * feed should emit no description element (rather than the variation's
	 * auto-generated attribute summary stored in post_excerpt).
	 *
	 * @group feed
	 */
	public function testDescriptionVariationFallsBackToEmptyWhenParentEmpty() {
		$description_method = $this->getProductsXmlFeedAttributeMethod( 'description' );

		$product           = new WC_Product_Variable();
		$variation_product = WC_Helper_Product::create_variation_product( $product );

		// Explicitly clear both parent descriptions.
		$variation_product->set_short_description( '' );
		$variation_product->set_description( '' );
		$variation_product->save();

		$child_id      = $variation_product->get_children()[0];
		$child_product = wc_get_product( $child_id );
		$xml           = $description_method( $child_product );

		// No description should be emitted; the variation attribute summary
		// must not leak into the feed.
		$this->assertEquals( '', $xml );
	}

	/**
	 * When the variation's parent product has been hard-deleted (orphaned
	 * variation), the fallback chain must short-circuit safely instead of
	 * throwing on the missing parent.
	 *
	 * @group feed
	 */
	public function testDescriptionVariationHandlesMissingParent() {
		$description_method = $this->getProductsXmlFeedAttributeMethod( 'description' );

		$product           = new WC_Product_Variable();
		$variation_product = WC_Helper_Product::create_variation_product( $product );
		$parent_id         = $variation_product->get_id();

		$child_id = $variation_product->get_children()[0];

		// Load the variation BEFORE hard-deleting the parent: WC cannot
		// instantiate a variation whose parent post is missing, so the load
		// has to happen first to capture a live variation object.
		$child_product = wc_get_product( $child_id );

		// Hard-delete the parent so wc_get_product( $parent_id ) returns false
		// when the fallback chain calls it from inside get_property_description.
		wp_delete_post( $parent_id, true );

		$xml = $description_method( $child_product );

		// No description and no fatal: the fallback chain bailed out cleanly.
		$this->assertEquals( '', $xml );
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
			function () {
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
		$mock_logger = $this->getMockLogger();

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
		$this->assertEquals(
			'<link><![CDATA[' . $this->getProductPermalinkWithUtm( $product ) . ']]></link>',
			$xml
		);
		$this->assertStringContainsString( 'utm_source=pinterest&utm_medium=social', $xml );
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
		$attachment    = array(
			'post_mime_type' => 'image/png',
			'post_title'     => 'product image',
		);
		$attachment_id = wp_insert_attachment( $attachment, 'product_image.png', $product->get_id() );

		// Add attachment id as product image id.
		$product->set_image_id( $attachment_id );
		$product->save();

		$xml = $image_link_method( $product );
		$this->assertEquals(
			'<g:image_link><![CDATA[' . wp_get_attachment_url( $attachment_id ) . ']]></g:image_link>',
			$xml
		);
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
		$product      = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => 15 ) );
		$xml          = $price_method( $product );
		$this->assertEquals( '<g:price>15.00USD</g:price>', $xml );

		// Test if the price excludes taxes.
		$old_tax_display_option = get_option( 'woocommerce_tax_display_shop' );
		update_option( 'woocommerce_tax_display_shop', 'excl' );

		$price_decimals_method = ( new ReflectionClass( ProductsXmlFeed::class ) )->getMethod( 'get_currency_decimals' );
		$price_decimals_method->setAccessible( true );
		$price_decimals = $price_decimals_method->invoke( null );

		$product_price   = wc_get_price_excluding_tax(
			$product,
			array(
				'price' => $product->get_regular_price(),
			)
		);
		$formatted_price = wc_format_decimal( $product_price, $price_decimals );
		$this->assertEquals( '<g:price>' . $formatted_price . get_woocommerce_currency() . '</g:price>', $xml );

		update_option( 'woocommerce_tax_display_shop', $old_tax_display_option );

		// Test with another currency.
		$old_currency = get_woocommerce_currency();
		update_option( 'woocommerce_currency', 'JPY' );
		$price_method = $this->getProductsXmlFeedAttributeMethod( 'g:price' );
		$product      = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => 15 ) );
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
		$product = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => 15 ) );
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

		// Test if the price excludes taxes.
		$old_tax_display_option = get_option( 'woocommerce_tax_display_shop' );
		update_option( 'woocommerce_tax_display_shop', 'excl' );

		$price_decimals_method = ( new ReflectionClass( ProductsXmlFeed::class ) )->getMethod( 'get_currency_decimals' );
		$price_decimals_method->setAccessible( true );
		$price_decimals = $price_decimals_method->invoke( null );

		$product_price   = wc_get_price_excluding_tax(
			$product,
			array(
				'price' => $product->get_sale_price(),
			)
		);
		$formatted_price = wc_format_decimal( $product_price, $price_decimals );
		$this->assertEquals( '<sale_price>' . $formatted_price . get_woocommerce_currency() . '</sale_price>', $xml );

		update_option( 'woocommerce_tax_display_shop', $old_tax_display_option );
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
	public function testPropertyPriceWithTaxesXML() {
		$price_method = $this->getProductsXmlFeedAttributeMethod( 'g:price' );
		$product      = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => 15 ) );

		// Setup shipping.
		$zone = ShippingHelpers::createZoneWithLocations(
			array(
				array( 'US', 'country' ),
			)
		);
		ShippingHelpers::addFlatRateShippingMethodToZone( $zone );
		ShippingHelpers::addFreeShipping( $zone );

		// Set customer address on Spain and set a 20% tax for Spain based customers.
		update_option( 'woocommerce_default_country', 'ES:B' );
		ShippingHelpers::addTaxRate( 'ES' );

		// Test if the price excludes taxes.
		$old_tax_display_option = get_option( 'woocommerce_tax_display_shop' );
		update_option( 'woocommerce_tax_display_shop', 'incl' );

		// Setup taxes.
		add_filter( 'wc_tax_enabled', '__return_true' );
		add_filter( 'woocommerce_prices_include_tax', '__return_true' );
		WC()->customer->set_shipping_location( 'US', 'CA' );

		// Edge case where price is wrong.
		$xml = $price_method( $product );
		$this->assertEquals( '<g:price>12.50USD</g:price>', $xml );

		// Apply the filter.
		add_filter( 'woocommerce_customer_taxable_address', array( $this, 'filter_taxable_location' ) );

		$xml = $price_method( $product );

		$this->assertEquals( '<g:price>15.00USD</g:price>', $xml );

		// Back to the default options.
		update_option( 'woocommerce_default_country', 'US:CA' );
		update_option( 'woocommerce_tax_display_shop', $old_tax_display_option );
	}

	/**
	 * @group feed
	 */
	public function testPropertyMpnXML() {
		$mpn_method = $this->getProductsXmlFeedAttributeMethod( 'g:mpn' );
		$product    = WC_Helper_Product::create_simple_product(
			true,
			array(
				'sku' => 'DUMMY SKU',
			)
		);
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
				'sku' => 'invalid&sku',
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
		$attachment      = array(
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
		$product->set_gallery_image_ids( array( $attachment_id_1, $attachment_id_2 ) );
		$product->save();

		$xml      = $additional_image_link_method( $product );
		$expected = sprintf(
			'<g:additional_image_link><![CDATA[%s,%s]]></g:additional_image_link>',
			wp_get_attachment_url( $attachment_id_1 ),
			wp_get_attachment_url( $attachment_id_2 )
		);

		$this->assertEquals( $expected, $xml );
	}

	/**
	 * @group feed
	 */
	public function testAttributesConditionXML() {
		$method = ( new ReflectionClass( ProductsXmlFeed::class ) )->getMethod( 'get_attributes_xml' );
		$method->setAccessible( true );

		$product = WC_Helper_Product::create_simple_product();
		$xml     = $method->invoke( null, $product, '' );
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
		$xml     = $method->invoke( null, $product, '' );
		// No attributes set, output should be empty.
		$this->assertEquals( '', $xml );

		$full_category_name_method = new ReflectionMethod( GoogleCategorySearch::class, 'full_category_name' );
		$full_category_name_method->setAccessible( true );
		$taxonomy           = GoogleProductTaxonomy::TAXONOMY[502979]; // Randomly selected category - i just made sure that it has parent.
		$full_taxonomy_name = $full_category_name_method->invoke( new GoogleCategorySearch(), $taxonomy );
		$condition          = new GoogleCategory( $full_taxonomy_name );
		$attribute_manager  = AttributeManager::instance();
		$attribute_manager->update( $product, $condition );

		$xml = $method->invoke( null, $product, '' );
		// Google product category attribute was set, we should see it in the output.
		$this->assertEquals( '<g:google_product_category>Arts &amp; Entertainment &gt; Hobbies &amp; Creative Arts &gt; Arts &amp; Crafts &gt; Art &amp; Craft Kits &gt; Jewelry Making Kits</g:google_product_category>' . PHP_EOL, $xml );
	}

	/**
	 * @group feed
	 *
	 * Test that the feed is writable.
	 */
	public function testFeedWritable() {
		// Create simple product.
		$product = WC_Helper_Product::create_simple_product();

		// We need header and footer so we can process XML directly.
		$xml  = ProductsXmlFeed::get_xml_header();
		$xml .= ProductsXmlFeed::get_xml_item( $product, 'US' );
		$xml .= ProductsXmlFeed::get_xml_footer();

		$feed_configurations = LocalFeedConfigs::get_instance();

		foreach ( $feed_configurations->get_configurations() as $config ) {
			$bytes_written = file_put_contents(
				$config['tmp_file'],
				$xml
			);

			$this->assertTrue( (bool) $bytes_written );

			break;
		}
	}

	/**
	 * Helper function for extracting the static private members of the ProductsXmlFeed class.
	 * Gets the property method then just pass the product and voila.
	 *
	 * @param string $attribute
	 * @return callable
	 */
	private function getProductsXmlFeedAttributeMethod( $attribute ) {
		$method_name = 'get_property_' . str_replace( ':', '_', $attribute );
		$class       = new ReflectionClass( ProductsXmlFeed::class );
		$method      = $class->getMethod( $method_name );
		$method->setAccessible( true );

		return function ( $product ) use ( $method, $attribute ) {
			return $method->invoke( null, $product, $attribute );
		};
	}

	/**
	 * Gets the product permalink with Pinterest UTM parameters.
	 *
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	private function getProductPermalinkWithUtm( $product ) {
		return add_query_arg(
			array(
				'utm_source' => 'pinterest',
				'utm_medium' => 'social',
			),
			$product->get_permalink()
		);
	}

	/**
	 * Gets a mock logger for testing.
	 *
	 * @return object Mock logger instance
	 */
	private function getMockLogger() {
		return new class() {
			/**
			 * The message to log.
			 *
			 * @var string
			 */
			public static $message = '';

			/**
			 * Constructor.
			 */
			public function __construct() {
				self::$message = '';
			}

			/**
			 * Log the message.
			 *
			 * @param string $level The level of the message.
			 * @param string $msg The message to log.
			 */
			public function log( $level, $msg ) {
				self::$message = $msg;
			}
		};
	}

	/**
	 * Test that product_type is limited to 5 categories
	 *
	 * @group feed
	 */
	public function testProductTypeLimitToFiveCategories() {
		$product_type_method = $this->getProductsXmlFeedAttributeMethod( 'g:product_type' );
		$product             = WC_Helper_Product::create_simple_product();

		/**
		 * Mock logger object that will catch any logged messages.
		 */
		$mock_logger = $this->getMockLogger();

		Logger::$logger = $mock_logger;

		// Create 7 categories and assign them to the product.
		$category_ids = array();
		for ( $i = 1; $i <= 7; $i++ ) {
			$category       = wp_insert_term( "Category {$i}", 'product_cat' );
			$category_ids[] = $category['term_id'];
		}
		wp_set_object_terms( $product->get_id(), $category_ids, 'product_cat' );

		$xml = $product_type_method( $product );

		// Should only have first 5 categories.
		$this->assertEquals( '<g:product_type>Category 1 &gt; Category 2 &gt; Category 3 &gt; Category 4 &gt; Category 5</g:product_type>', $xml );

		// Check that a warning was logged.
		$this->assertStringContainsString( 'has 7 categories, limiting to first 5', $mock_logger::$message );
	}

	/**
	 * Test that product_type is limited to 1000 characters
	 *
	 * @group feed
	 */
	public function testProductTypeCharacterLimit() {
		$product_type_method = $this->getProductsXmlFeedAttributeMethod( 'g:product_type' );
		$product             = WC_Helper_Product::create_simple_product();

		/**
		 * Mock logger object that will catch any logged messages.
		 */
		$mock_logger = $this->getMockLogger();

		Logger::$logger = $mock_logger;

		// Create categories with long names to exceed 1000 character limit.
		// Each category name is 300 chars, so 4 categories = 1200 chars + separators.
		$category_ids = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$long_name      = str_repeat( "LongCategoryName{$i}_", 11 ); // ~242 chars each.
			$category       = wp_insert_term( $long_name, 'product_cat' );
			$category_ids[] = $category['term_id'];
		}
		wp_set_object_terms( $product->get_id(), $category_ids, 'product_cat' );

		$xml = $product_type_method( $product );

		// Extract the product_type value from the XML.
		preg_match( '/<g:product_type>(.*?)<\/g:product_type>/', $xml, $matches );
		$product_type = $matches[1] ?? '';

		// Decode HTML entities to get the actual length.
		$product_type_decoded = html_entity_decode( $product_type );

		// Should be under 1000 characters.
		$this->assertLessThanOrEqual( 1000, strlen( $product_type_decoded ) );

		// Check that a warning was logged.
		$this->assertStringContainsString( 'product_type length is', $mock_logger::$message );
		$this->assertStringContainsString( 'truncating to 1000 characters', $mock_logger::$message );
	}

	/**
	 * Test that product_type with exactly 5 categories doesn't trigger warning
	 *
	 * @group feed
	 */
	public function testProductTypeWithFiveCategoriesNoWarning() {
		$product_type_method = $this->getProductsXmlFeedAttributeMethod( 'g:product_type' );
		$product             = WC_Helper_Product::create_simple_product();

		/**
		 * Mock logger object that will catch any logged messages.
		 */
		$mock_logger = $this->getMockLogger();

		Logger::$logger = $mock_logger;

		// Create exactly 5 categories.
		$category_ids = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$category       = wp_insert_term( "Category {$i}", 'product_cat' );
			$category_ids[] = $category['term_id'];
		}
		wp_set_object_terms( $product->get_id(), $category_ids, 'product_cat' );

		$xml = $product_type_method( $product );

		// Should have all 5 categories.
		$this->assertEquals( '<g:product_type>Category 1 &gt; Category 2 &gt; Category 3 &gt; Category 4 &gt; Category 5</g:product_type>', $xml );

		// No warning should be logged for exactly 5 categories.
		$this->assertEquals( '', $mock_logger::$message );
	}

	/**
	 * Mimic the method on FeedGenerator.
	 *
	 * @param array $taxable_location The taxable location to filter.
	 */
	public function filter_taxable_location( array $taxable_location ) {

		if ( isset( $taxable_location[0] ) ) {
			$taxable_location[0] = Pinterest_For_Woocommerce()::get_base_country( null );
		}

		return $taxable_location;
	}

	/**
	 * Remove filters and shortcodes.
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Remove any added filter.
		remove_all_filters( 'pinterest_for_woocommerce_product_description_apply_shortcodes' );

		// Remove added shortcodes.
		remove_shortcode( 'pinterest_for_woocommerce_sample_test_shortcode' );

		// Reset logger.
		Logger::$logger = null;
	}
}
