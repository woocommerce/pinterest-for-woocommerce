<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\Admin\Admin;
use Automattic\WooCommerce\Pinterest\Admin\Product\Attributes\AttributesTab;
use Automattic\WooCommerce\Pinterest\Product\Attributes\AttributeManager;
use Automattic\WooCommerce\Pinterest\Product\Attributes\Condition;
use Automattic\WooCommerce\Pinterest\Product\Attributes\GoogleCategory;
use Automattic\WooCommerce\Pinterest\View\PHPViewFactory;
use WC_Helper_Product;
use WP_UnitTestCase;

/**
 * Product attributes are ingested only by an authorized editor save.
 */
class AttributesTabTest extends WP_UnitTestCase {

	/** @var array Original form data. */
	private $original_post;

	/**
	 * Register the native tab with an isolated form request.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->original_post = $_POST; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- Restore caller-owned request data.
		$_POST               = array();
		( new AttributesTab( new Admin( new PHPViewFactory() ) ) )->register();
	}

	/**
	 * Restore the caller's request data.
	 */
	public function tearDown(): void {
		$_POST = $this->original_post;
		parent::tearDown();
	}

	/**
	 * @dataProvider product_save_cases
	 * @param string $context Native save context.
	 * @param bool   $allowed Whether form changes are authorized.
	 */
	public function test_product_attributes_require_editor_authorization( $context, $allowed ) {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 3 );
		if ( 'native_new' === $context ) {
			$product->set_status( 'auto-draft' );
		}
		$product->save();
		$manager = AttributeManager::instance();
		$manager->update( $product, new Condition( 'new' ) );
		$manager->update( $product, new GoogleCategory( 'Original category' ) );
		$user = self::factory()->user->create( array( 'role' => 'customer' === $context ? 'customer' : 'administrator' ) );
		wp_set_current_user( $user );
		$_POST = array(
			'woocommerce_meta_nonce' => wp_create_nonce( 'woocommerce_save_data' ),
			'post_ID'                => (string) $product->get_id(),
			'product-type'           => 'simple',
			'_regular_price'         => '12',
			'pinterest_attributes'   => array(
				'condition'               => 'clear' === $context ? '' : 'used',
				'google_product_category' => 'clear' === $context ? '' : 'Updated category',
			),
		);
		if ( 'missing_nonce' === $context ) {
			unset( $_POST['woocommerce_meta_nonce'] );
		} elseif ( 'invalid_nonce' === $context ) {
			$_POST['woocommerce_meta_nonce'] = 'invalid-local-nonce';
		} elseif ( 'array_nonce' === $context ) {
			$_POST['woocommerce_meta_nonce'] = array( 'invalid-local-nonce' );
		} elseif ( 'absent_form' === $context ) {
			unset( $_POST['pinterest_attributes'] );
		}
		$deny = function ( $caps, $cap, $user_id, $args ) use ( $product, $context ) {
			return 'denied_product' === $context && 'edit_post' === $cap && $product->get_id() === (int) ( $args[0] ?? 0 ) ? array( 'do_not_allow' ) : $caps;
		};
		add_filter( 'map_meta_cap', $deny, 10, 4 );
		try {
			if ( 'generic' === $context ) {
				$product->set_regular_price( '12' );
				$product->save();
			} elseif ( 'stock' === $context ) {
				wc_update_product_stock( $product, 1, 'decrease' );
			} elseif ( in_array( $context, array( 'native_editor', 'native_new' ), true ) ) {
				require_once WC_ABSPATH . 'includes/admin/class-wc-admin-meta-boxes.php';
				require_once WC_ABSPATH . 'includes/admin/meta-boxes/class-wc-meta-box-product-data.php';
				\WC_Meta_Box_Product_Data::save( $product->get_id(), get_post( $product->get_id() ) );
			} else {
				/**
				 * This native WooCommerce hook processes the editor's product object.
				 * phpcs:disable WooCommerce.Commenting.CommentHooks.MissingSinceComment
				 */
				do_action( 'woocommerce_admin_process_product_object', $product );
				// phpcs:enable WooCommerce.Commenting.CommentHooks.MissingSinceComment
			}
		} finally {
			remove_filter( 'map_meta_cap', $deny, 10 );
		}
		$saved = wc_get_product( $product->get_id() );
		$this->assertSame( 'clear' === $context ? null : ( $allowed ? 'used' : 'new' ), $manager->get_value( $saved, 'condition' ) );
		$this->assertSame( 'clear' === $context ? null : ( $allowed ? 'Updated category' : 'Original category' ), $manager->get_value( $saved, 'google_product_category' ) );
		if ( 'generic' === $context ) {
			$created = WC_Helper_Product::create_simple_product();
			$this->assertNull( $manager->get_value( $created, 'condition' ) );
			$this->assertNull( $manager->get_value( $created, 'google_product_category' ) );
		}
		if ( 'stock' === $context ) {
			$this->assertSame( 2, $saved->get_stock_quantity() );
		}
	}

	/**
	 * Native editor and unrelated product-save paths.
	 *
	 * @return array
	 */
	public function product_save_cases() {
		return array(
			'authorized editor'    => array( 'editor', true ),
			'native editor save'   => array( 'native_editor', true ),
			'new editor product'   => array( 'native_new', true ),
			'clear attributes'     => array( 'clear', true ),
			'customer'             => array( 'customer', false ),
			'exact product denied' => array( 'denied_product', false ),
			'missing nonce'        => array( 'missing_nonce', false ),
			'invalid nonce'        => array( 'invalid_nonce', false ),
			'non-string nonce'     => array( 'array_nonce', false ),
			'absent form'          => array( 'absent_form', false ),
			'generic product save' => array( 'generic', false ),
			'stock update'         => array( 'stock', false ),
		);
	}
}
