<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\RichPins;
use Pinterest_For_Woocommerce;
use WC_Helper_Product;
use WP_UnitTestCase;

/**
 * Rich Pins product-password access tests.
 */
class RichPinsTest extends WP_UnitTestCase {

	/**
	 * The product-password cookie before the test.
	 *
	 * @var mixed
	 */
	private $password_cookie;

	/**
	 * Set up native product Rich Pins.
	 */
	public function setUp(): void {
		parent::setUp();
		// Preserve the exact caller cookie for teardown.
		$this->password_cookie = $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );
		Pinterest_For_Woocommerce::save_setting( 'rich_pins_on_products', true );
	}

	/**
	 * Restore the request cookie.
	 */
	public function tearDown(): void {
		if ( null === $this->password_cookie ) {
			unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );
		} else {
			$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = $this->password_cookie;
		}
		parent::tearDown();
	}

	/**
	 * Product metadata follows native password access.
	 *
	 * @dataProvider password_access_cases
	 * @param string $access Password access state.
	 */
	public function test_product_metadata_respects_password_access( $access ) {
		$product = WC_Helper_Product::create_simple_product(
			true,
			array( 'short_description' => 'Local product description.' )
		);
		if ( 'public' !== $access ) {
			wp_update_post(
				array(
					'ID'            => $product->get_id(),
					'post_password' => 'local-product-password',
				)
			);
		}
		if ( in_array( $access, array( 'unlocked', 'wrong' ), true ) ) {
			require_once ABSPATH . WPINC . '/class-phpass.php';
			$hasher                                 = new \PasswordHash( 8, true );
			$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = $hasher->HashPassword( 'unlocked' === $access ? 'local-product-password' : 'wrong-password' );
		}

		$this->go_to( get_permalink( $product->get_id() ) );
		$locked = in_array( $access, array( 'locked', 'wrong' ), true );
		$this->assertSame( $locked, post_password_required( $product->get_id() ) );

		ob_start();
		try {
			RichPins::maybe_inject_rich_pins_opengraph_tags();
			$output = ob_get_contents();
		} finally {
			ob_end_clean();
		}

		if ( $locked ) {
			$this->assertSame( '<meta name="pinterest-rich-pin" content="false" />', $output );
			$this->assertSame(
				array( 'caller-field' => 'kept' ),
				RichPins::add_product_opengraph_tags(
					array( 'caller-field' => 'kept' ),
					array(
						'products' => array(
							'enabled'            => true,
							'enable_description' => true,
						),
					)
				)
			);
		} else {
			$this->assertStringContainsString( 'content="Local product description."', $output );
			$this->assertStringContainsString( 'property="product:price:amount"', $output );
		}
	}

	/**
	 * Native visitor access states.
	 *
	 * @return array
	 */
	public function password_access_cases() {
		return array(
			'no password supplied'    => array( 'locked' ),
			'wrong password supplied' => array( 'wrong' ),
			'correct password'        => array( 'unlocked' ),
			'public product'          => array( 'public' ),
		);
	}
}
