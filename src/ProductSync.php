<?php //phpcs:disable WordPress.WP.AlternativeFunctions --- Uses FS read/write in order to reliable append to an existing file.
/**
 * Pinterest for WooCommerce Catalog Syncing
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionScheduler as ActionSchedulerProxy;
use Automattic\WooCommerce\Pinterest\Utilities\ProductFeedLogger;
use WC_Product;

/**
 * Class Handling registration & generation of the XML product feed.
 */
class ProductSync {

	use ProductFeedLogger;

	/**
	 * Feed File Generator Instance
	 *
	 * @var $feed_generator FeedGenerator
	 */
	private static $feed_generator = null;


	/**
	 * Feed File Generator Instance
	 *
	 * @var $feed_registration FeedRegistration
	 */
	private static $feed_registration = null;


	/**
	 * Local Feed Configurations class.
	 *
	 * @var $configurations LocalFeedConfigs
	 */
	private static $configurations = null;

	/**
	 * Product properties the XML feed renders.
	 *
	 * Only meta backed properties are listed. WooCommerce collects the updated props in
	 * WC_Product_Data_Store_CPT::update_post_meta(), while post fields (name, description,
	 * slug, status) go through wp_update_post() and are covered by the edit_post hook.
	 *
	 * stock_quantity is deliberately absent: the only stock related field in the feed is
	 * g:availability, which ProductsXmlFeed derives from the stock status, so a quantity
	 * decrement at checkout produces a byte identical feed.
	 *
	 * @since 1.5.1
	 *
	 * @var string[]
	 */
	const FEED_RELEVANT_PRODUCT_PROPS = array(
		'regular_price',
		'sale_price',
		'date_on_sale_from',
		'date_on_sale_to',
		'stock_status',
		'sku',
		'tax_status',
		'tax_class',
		'image_id',
		'gallery_image_ids',
		'virtual',
	);

	/**
	 * Products already flagged during this request, keyed by notification source and product ID.
	 *
	 * Several hooks fire for a single save, and imports, bulk edits or wc_scheduled_sales
	 * save many products in one request, so a product notifies the generator once while the
	 * flag it wrote is still set. The guard is not trusted once a generation cycle has consumed
	 * the flag: long-lived CLI and Action Scheduler workers save the same product again later
	 * and must be able to flag the feed again.
	 *
	 * @since 1.5.1
	 *
	 * @var array<string, bool>
	 */
	private static $flagged_product_ids = array();

	/**
	 * Initiate class.
	 */
	public static function maybe_init() {

		add_action( 'update_option_' . PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME, array( __CLASS__, 'maybe_deregister' ), 10, 2 );
		if ( ! self::is_product_sync_enabled() ) {
			return;
		}

		self::initialize_feed_components();
		/**
		 * Mark feed as needing re-generation whenever a product is edited or changed.
		 *
		 * The edit_post hook covers the post fields and any status transition, but it fires
		 * before WooCommerce writes the product meta and does not fire at all for meta only
		 * saves. The woocommerce_product_object_updated_props hook covers those, filtered
		 * down to the properties the feed renders so that saves which cannot change the feed
		 * (a stock quantity decrement at checkout, a sales counter bump) do not schedule a
		 * regeneration. Creates never reach edit_post, hence woocommerce_new_product.
		 */
		add_action( 'edit_post', array( __CLASS__, 'mark_feed_dirty' ), 10, 1 );
		add_action( 'woocommerce_new_product', array( __CLASS__, 'mark_feed_dirty_on_new_product' ), 10, 1 );
		add_action( 'woocommerce_product_object_updated_props', array( __CLASS__, 'mark_feed_dirty_on_updated_props' ), 10, 2 );

		if ( 'yes' === get_option( 'woocommerce_manage_stock' ) ) {
			add_action( 'woocommerce_variation_set_stock_status', array( __CLASS__, 'mark_feed_dirty' ), 10, 1 );
			add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'mark_feed_dirty' ), 10, 1 );
		}

		/**
		 * Mark feed as needing re-generation on changes to the woocommerce_hide_out_of_stock_items setting
		 */
		add_action(
			'update_option_woocommerce_hide_out_of_stock_items',
			function () {
				self::$feed_generator->mark_feed_dirty();
			}
		);

		/**
		 * Mark feed as needing re-generation on changes to the woocommerce_tax_display_shop or woocommerce_tax_display_cart settings
		 */
		add_action(
			'update_option_woocommerce_tax_display_shop',
			function () {
				self::$feed_generator->mark_feed_dirty();
			}
		);
		add_action(
			'update_option_woocommerce_tax_display_cart',
			function () {
				self::$feed_generator->mark_feed_dirty();
			}
		);
	}

	/**
	 * Observe Pinterest option change and decide if we need to deregister.
	 *
	 * @since 1.0.10
	 *
	 * @param array $old_value Option old value.
	 * @param array $value     Option new value.
	 */
	public static function maybe_deregister( $old_value, $value ) {
		if ( ! is_array( $value ) ) {
			return;
		}

		$isset             = isset( $value['product_sync_enabled'] );
		$has_changed       = $isset && ( $old_value['product_sync_enabled'] ?? '' ) !== $value['product_sync_enabled'];
		$should_deregister = $has_changed && false === $value['product_sync_enabled'];
		if ( $should_deregister ) {
			self::deregister();
		}
	}

	/**
	 * Initialize components of the synchronization process.
	 *
	 * @since 1.0.10
	 */
	private static function initialize_feed_components() {
		self::$configurations    = LocalFeedConfigs::get_instance();
		$action_scheduler        = new ActionSchedulerProxy();
		$feed_file_operations    = new FeedFileOperations( self::$configurations );
		self::$feed_generator    = new FeedGenerator( $action_scheduler, $feed_file_operations, self::$configurations );
		self::$feed_registration = new FeedRegistration( self::$configurations, $feed_file_operations );

		self::$feed_registration->init();
		self::$feed_generator->init();
	}

	/**
	 * Checks if the feature is enabled, and all requirements are met.
	 *
	 * @return boolean
	 */
	public static function is_product_sync_enabled() {
		$domain_verified = Pinterest_For_Woocommerce()::is_domain_verified();

		return (bool) $domain_verified && Pinterest_For_Woocommerce()::get_setting( 'product_sync_enabled' );
	}

	/**
	 * Get the proper extra_info for the feed status.
	 *
	 * @return string
	 */
	public static function get_feed_status_extra_info() {

		if ( self::is_product_sync_enabled() ) {
			return '';
		}

		if ( ! Pinterest_For_Woocommerce()::is_domain_verified() ) {
			return sprintf(
				/* translators: 1: The URL of the connection page */
				__( 'The domain is not verified, visit the <a href="%1$s">connection</a> page to verify it.', 'pinterest-for-woocommerce' ),
				esc_url(
					add_query_arg(
						array(
							'page' => 'wc-admin',
							'path' => '/pinterest/connection',
						),
						admin_url( 'admin.php' )
					)
				)
			);
		}

		if ( ! Pinterest_For_Woocommerce()::is_tracking_configured() ) {
			return sprintf(
				/* translators: 1: The URL of the connection page */
				__( 'The tracking tag is not configured, visit the <a href="%1$s">connection</a> page to configure it.', 'pinterest-for-woocommerce' ),
				esc_url(
					add_query_arg(
						array(
							'page' => 'wc-admin',
							'path' => '/pinterest/connection',
						),
						admin_url( 'admin.php' )
					)
				)
			);
		}

		return sprintf(
			/* translators: 1: The URL of the settings page */
			__( 'Visit the <a href="%1$s">settings</a> page to enable it.', 'pinterest-for-woocommerce' ),
			esc_url(
				add_query_arg(
					array(
						'page' => 'wc-admin',
						'path' => '/pinterest/settings',
					),
					admin_url( 'admin.php' )
				)
			)
		);
	}

	/**
	 * Handles de-registration of the feed.
	 *
	 * @return void
	 */
	public static function deregister() {
		FeedGenerator::deregister();
		LocalFeedConfigs::deregister();
		FeedRegistration::deregister();
		ProductFeedStatus::deregister();

		self::log( 'Product feed reset and files deleted.' );
	}

	/**
	 * Stop jobs on deactivation.
	 */
	public static function cancel_jobs() {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		FeedGenerator::cancel_jobs();
		FeedRegistration::cancel_jobs();
	}

	/**
	 * Check if Given ID is of a product and if yes, mark feed as dirty.
	 *
	 * @param integer $product_id The product ID.
	 *
	 * @return void
	 */
	public static function mark_feed_dirty( $product_id ) {
		self::mark_feed_dirty_once( $product_id, 'post' );
	}

	/**
	 * Marks the feed dirty at most once per product and notification source while the flag is set.
	 *
	 * The sources are deduplicated separately on purpose. A product save notifies through
	 * edit_post before WooCommerce writes the meta and through
	 * woocommerce_product_object_updated_props afterwards, and a generation cycle can consume
	 * the flag in between, so the post-write notification has to be able to set it again.
	 *
	 * The guard only stands while the flag this process wrote is still set. Once a cycle has
	 * consumed it, the same notification writes it again; otherwise a long-lived worker would
	 * never flag a product twice and its later changes would wait for the daily run.
	 *
	 * @since 1.5.1
	 *
	 * @param integer $product_id The product ID.
	 * @param string  $source     Identifier of the notification source.
	 *
	 * @return void
	 */
	private static function mark_feed_dirty_once( $product_id, $source ) {
		$product_id = (int) $product_id;
		$key        = $source . ':' . $product_id;

		if ( isset( self::$flagged_product_ids[ $key ] ) && self::$feed_generator->feed_is_dirty() ) {
			return;
		}

		if ( ! wc_get_product( $product_id ) ) {
			return;
		}

		self::$flagged_product_ids[ $key ] = true;

		self::$feed_generator->mark_feed_dirty();
	}

	/**
	 * Mark the feed as dirty for a newly created product.
	 *
	 * @since 1.5.1
	 *
	 * @param integer $product_id The product ID.
	 *
	 * @return void
	 */
	public static function mark_feed_dirty_on_new_product( $product_id ) {
		self::mark_feed_dirty_if_in_feed( wc_get_product( $product_id ), 'new_product' );
	}

	/**
	 * Mark the feed as dirty when a save wrote a property the feed renders.
	 *
	 * WooCommerce fires this for every product data store write, so the properties are
	 * filtered against FEED_RELEVANT_PRODUCT_PROPS to skip the saves that cannot change
	 * the feed output.
	 *
	 * @since 1.5.1
	 *
	 * @param WC_Product $product       The saved product.
	 * @param array      $updated_props Names of the properties written by the save.
	 *
	 * @return void
	 */
	public static function mark_feed_dirty_on_updated_props( $product, $updated_props ) {
		if ( ! is_array( $updated_props ) || ! array_intersect( $updated_props, self::FEED_RELEVANT_PRODUCT_PROPS ) ) {
			return;
		}

		self::mark_feed_dirty_if_in_feed( $product, 'updated_props' );
	}

	/**
	 * Mark the feed as dirty only for products the feed can include.
	 *
	 * FeedGenerator::get_items_for_batch() reads published products only, so a draft or a
	 * pending product cannot change the feed. Products leaving the published state are not
	 * filtered here: they travel through wp_update_post(), which fires edit_post.
	 *
	 * @since 1.5.1
	 *
	 * @param WC_Product|false|null $product The product, or a falsy value when the lookup failed.
	 * @param string                $source  Identifier of the notification source.
	 *
	 * @return void
	 */
	private static function mark_feed_dirty_if_in_feed( $product, $source ) {
		if ( ! $product instanceof WC_Product || 'publish' !== $product->get_status() ) {
			return;
		}

		self::mark_feed_dirty_once( $product->get_id(), $source );
	}
}
