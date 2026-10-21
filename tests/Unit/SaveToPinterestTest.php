<?php
/**
 * Tests the Save to Pinterest button markup printed on the storefront.
 *
 * @package Automattic\WooCommerce\Pinterest\Tests
 */

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\SaveToPinterest;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Pinterest_For_Woocommerce;
use WC_Helper_Product;
use WP_UnitTestCase;

/**
 * Renders the button through the storefront hooks and checks the link it prints.
 *
 * The link's query string is held to the URL standard only: it must be valid percent-encoding
 * that decodes back to the product's name, address and image. How Pinterest's own script reads
 * the link is not simulated here.
 *
 * @version 1.6.0
 */
class SaveToPinterestTest extends WP_UnitTestCase {

	/**
	 * The product global of the calling suite.
	 *
	 * @var mixed
	 */
	private $previous_product;

	/**
	 * Connects a business account with a verified domain and turns the button on.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->previous_product = $GLOBALS['product'] ?? null;

		Pinterest_For_Woocommerce::save_token_data( array( 'access_token' => 'access-token' ) );
		Pinterest_For_Woocommerce::save_settings(
			array(
				'save_to_pinterest' => true,
				'account_data'      => array(
					'is_partner'             => true,
					'verified_user_websites' => array( wp_parse_url( home_url(), PHP_URL_HOST ) ),
				),
			)
		);
	}

	/**
	 * Restores the product global and the plugin's in-memory settings, and deletes the uploaded image.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$GLOBALS['product'] = $this->previous_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the caller's value.
		$this->remove_added_uploads();

		parent::tearDown();

		Pinterest_For_Woocommerce::get_settings( true );
		Pinterest_For_Woocommerce::get_settings( true, PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );
	}

	/**
	 * A product with an image gets a link that pins that image, the product page and its name.
	 *
	 * @return void
	 */
	public function test_single_product_page_prints_a_pin_link_for_the_product_and_its_image() {
		$product  = WC_Helper_Product::create_simple_product( true, array( 'name' => 'Blue Mug' ) );
		$image_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$product->set_image_id( $image_id );
		$product->save();

		$wrapper = $this->render_on_hook( 'woocommerce_before_single_product_summary', $product );

		$this->assertSame( 'pinterest-for-woocommerce-image-wrapper', $wrapper->getAttribute( 'class' ) );
		$this->assertSame( 'Blue Mug to Pinterest (opens in a new window)', $this->text( $this->screen_reader_label( $wrapper ) ) );
		// The script the button ships with removes this hint by its class when the pin opens in a dialog instead.
		$this->assertSame( ' (opens in a new window)', $this->text( $this->first( $wrapper, './/span[@class="pinterest-for-woocommerce-new-window-hint"]' ) ) );

		$link = $this->pin_link( $wrapper );
		$this->assertSame( '', $this->text( $link ) );
		$this->assertSame( 'https://www.pinterest.com/pin/create/button/', strtok( $link->getAttribute( 'href' ), '?' ) );
		$this->assertSame(
			array(
				'description' => 'Blue Mug',
				'url'         => get_permalink( $product->get_id() ),
				// The upload is smaller than the "large" size, so its original file is the one to pin.
				'media'       => wp_get_attachment_url( $image_id ),
			),
			$this->decoded_query( $link )
		);
	}

	/**
	 * A product without an image gets no image to pin and no promise of a new window.
	 *
	 * @return void
	 */
	public function test_shop_loop_prints_a_pin_link_without_media_for_a_product_without_an_image() {
		$product = WC_Helper_Product::create_simple_product( true, array( 'name' => 'Plain Mug' ) );

		$wrapper = $this->render_on_hook( 'woocommerce_before_shop_loop_item', $product );

		// WooCommerce opens the product link on this hook at priority 10; a link inside a link is invalid markup.
		$this->assertLessThan( 10, has_action( 'woocommerce_before_shop_loop_item', array( SaveToPinterest::class, 'render_product_pin' ) ) );

		$this->assertSame( 'Plain Mug to Pinterest', $this->text( $this->screen_reader_label( $wrapper ) ) );
		$this->assertSame(
			array(
				'description' => 'Plain Mug',
				'url'         => get_permalink( $product->get_id() ),
			),
			$this->decoded_query( $this->pin_link( $wrapper ) )
		);
	}

	/**
	 * Characters that are special in a URL or in HTML come out of the link exactly as they went in.
	 *
	 * @return void
	 */
	public function test_pin_link_carries_the_whole_name_and_address_whatever_characters_they_contain() {
		$product = WC_Helper_Product::create_simple_product( true, array( 'name' => 'Salt & Pepper "Duo" = 50% off #1 < 2kg' ) );

		// A media library file whose stored name was never sanitized, as files imported or uploaded long ago can be.
		$image_id = self::factory()->attachment->create(
			array(
				'file'           => '2019/03/salt & pepper 100%.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);

		// WordPress addresses a product that is not published yet with two query arguments.
		$product->set_status( 'draft' );
		$product->set_image_id( $image_id );
		$product->save();
		$permalink = get_permalink( $product->get_id() );
		$this->assertSame( home_url( '/?post_type=product&p=' . $product->get_id() ), $permalink );

		$wrapper = $this->render_on_hook( 'woocommerce_before_shop_loop_item', $product );

		$this->assertSame(
			array(
				// WordPress typesets the straight quotes of a title as curly ones.
				'description' => "Salt & Pepper \u{201C}Duo\u{201D} = 50% off #1 < 2kg",
				'url'         => $permalink,
				'media'       => wp_upload_dir()['baseurl'] . '/2019/03/salt & pepper 100%.jpg',
			),
			$this->decoded_query( $this->pin_link( $wrapper ) )
		);
		$this->assertSame( "Salt & Pepper \u{201C}Duo\u{201D} = 50% off #1 < 2kg to Pinterest (opens in a new window)", $this->text( $this->screen_reader_label( $wrapper ) ) );
	}

	/**
	 * Product grid blocks get the button inside each product's list item, next to what was there.
	 *
	 * @return void
	 */
	public function test_product_grid_block_item_gets_the_pin_link_appended() {
		$product = WC_Helper_Product::create_simple_product( true, array( 'name' => 'Grid Mug' ) );
		SaveToPinterest::maybe_init();

		/**
		 * The filter WooCommerce Blocks passes each product grid item through.
		 *
		 * @since 1.0.0
		 */
		$html = apply_filters(
			'woocommerce_blocks_product_grid_item_html',
			'<li class="wc-block-grid__product"><a href="#">Grid Mug</a></li>',
			(object) array(),
			$product
		);

		$this->assertStringStartsWith( '<li class="wc-block-grid__product"><a href="#">Grid Mug</a><div class="pinterest-for-woocommerce-image-wrapper">', $html );
		$this->assertStringEndsWith( '</div></li>', $html );
		$this->assertSame(
			array(
				'description' => 'Grid Mug',
				'url'         => get_permalink( $product->get_id() ),
			),
			$this->decoded_query( $this->pin_link( $this->parse( $html ) ) )
		);
	}

	/**
	 * A product grid item without a product keeps its markup instead of being blanked.
	 *
	 * @return void
	 */
	public function test_product_grid_block_item_without_a_product_is_passed_through() {
		SaveToPinterest::maybe_init();

		/**
		 * The filter WooCommerce Blocks passes each product grid item through.
		 *
		 * @since 1.0.0
		 */
		$html = apply_filters(
			'woocommerce_blocks_product_grid_item_html',
			'<li class="wc-block-grid__product"></li>',
			(object) array(),
			null
		);

		$this->assertSame( '<li class="wc-block-grid__product"></li>', $html );
	}

	/**
	 * With the setting off, or before the store is fully set up, nothing is added to the storefront.
	 *
	 * @dataProvider provide_stores_without_the_button
	 *
	 * @param array $settings Settings that replace the ones of a store showing the button.
	 * @return void
	 */
	public function test_store_without_the_button_prints_nothing( array $settings ) {
		Pinterest_For_Woocommerce::save_settings( $settings );

		SaveToPinterest::maybe_init();

		$this->assertFalse( has_action( 'woocommerce_before_single_product_summary', array( SaveToPinterest::class, 'render_product_pin' ) ) );
		$this->assertFalse( has_action( 'woocommerce_before_shop_loop_item', array( SaveToPinterest::class, 'render_product_pin' ) ) );
		$this->assertFalse( has_filter( 'woocommerce_blocks_product_grid_item_html', array( SaveToPinterest::class, 'add_to_wc_blocks' ) ) );
		$this->assertFalse( has_action( 'wp_enqueue_scripts', array( SaveToPinterest::class, 'enqueue_scripts' ) ) );
	}

	/**
	 * Stores that must not show the button.
	 *
	 * @return array[]
	 */
	public function provide_stores_without_the_button(): array {
		$verified = array( 'verified_user_websites' => array( 'example.org' ) );

		return array(
			'setting turned off'  => array(
				array(
					'save_to_pinterest' => false,
					'account_data'      => array( 'is_partner' => true ) + $verified,
				),
			),
			'no business account' => array(
				array(
					'save_to_pinterest' => true,
					'account_data'      => array( 'is_partner' => false ) + $verified,
				),
			),
			'domain not verified' => array(
				array(
					'save_to_pinterest' => true,
					'account_data'      => array(
						'is_partner'             => true,
						'verified_user_websites' => array(),
					),
				),
			),
		);
	}

	/**
	 * Prints the button the way a storefront template does: on a WooCommerce hook, for the loop's product.
	 *
	 * @param string      $hook    WooCommerce template hook.
	 * @param \WC_Product $product Product the loop is on.
	 * @return DOMElement The button's wrapper.
	 */
	private function render_on_hook( string $hook, \WC_Product $product ): DOMElement {
		$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The loop global the hook reads.

		SaveToPinterest::maybe_init();

		// Only the button's own callback is run: WooCommerce prints images and links of its own on these hooks.
		ob_start();
		try {
			SaveToPinterest::render_product_pin();
			$output = ob_get_contents();
		} finally {
			ob_end_clean();
		}

		$this->assertNotFalse( has_action( $hook, array( SaveToPinterest::class, 'render_product_pin' ) ), "The button is not printed on $hook." );
		$this->assertNotFalse( has_action( 'wp_enqueue_scripts', array( SaveToPinterest::class, 'enqueue_scripts' ) ), 'The script that finishes the button is not enqueued.' );

		return $this->parse( $output );
	}

	/**
	 * Parses printed markup like a browser, which also decodes the attribute values.
	 *
	 * @param string $html Markup.
	 * @return DOMElement The button's wrapper.
	 */
	private function parse( string $html ): DOMElement {
		$document = new DOMDocument();
		$document->loadHTML( '<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_NOERROR );

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
		return $this->first( $document->documentElement, '//div[contains(@class,"pinterest-for-woocommerce-image-wrapper")]' );
	}

	/**
	 * The only element matching an XPath query.
	 *
	 * @param DOMElement $context Element to search in.
	 * @param string     $query   XPath query.
	 * @return DOMElement
	 */
	private function first( DOMElement $context, string $query ): DOMElement {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
		$matches = ( new DOMXPath( $context->ownerDocument ) )->query( $query, $context );
		$this->assertSame( 1, $matches->length, "Expected exactly one match for $query." );

		return $matches->item( 0 );
	}

	/**
	 * The text an element shows, with its markup resolved.
	 *
	 * @param DOMElement $element Element.
	 * @return string
	 */
	private function text( DOMElement $element ): string {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
		return $element->textContent;
	}

	/**
	 * The text read out for the button. The button's script looks it up by this class.
	 *
	 * @param DOMElement $wrapper The button's wrapper.
	 * @return DOMElement
	 */
	private function screen_reader_label( DOMElement $wrapper ): DOMElement {
		return $this->first( $wrapper, './span[@class="screen-reader-text"]' );
	}

	/**
	 * The link Pinterest's script looks for, by this attribute, and replaces with the button.
	 *
	 * @param DOMElement $wrapper The button's wrapper.
	 * @return DOMElement
	 */
	private function pin_link( DOMElement $wrapper ): DOMElement {
		return $this->first( $wrapper, './a[@data-pin-do="buttonPin"]' );
	}

	/**
	 * Decodes the link's query string with PHP's own URL functions.
	 *
	 * @param DOMElement $link The pin link.
	 * @return string[] Decoded values by parameter, in the order they appear.
	 */
	private function decoded_query( DOMElement $link ): array {
		$query = (string) wp_parse_url( $link->getAttribute( 'href' ), PHP_URL_QUERY );

		// RFC 3986: a query holds unreserved characters, sub-delimiters, ":", "@", "/", "?" and percent-encoded octets.
		$this->assertMatchesRegularExpression( '/^(?:[A-Za-z0-9\-._~!$&\'()*+,;=:@\/?]|%[0-9A-Fa-f]{2})+$/', $query, 'The link\'s query string is not valid percent-encoding.' );

		parse_str( $query, $values );

		return $values;
	}
}
