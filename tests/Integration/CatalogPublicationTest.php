<?php
/**
 * Native scheduled catalog publication and recovery.
 *
 * @package Pinterest_For_WooCommerce
 */

// phpcs:disable WordPress.WP.AlternativeFunctions -- Native file I/O and its failure behavior are under test.

namespace Automattic\WooCommerce\Pinterest\Tests\Integration;

use ActionScheduler;
use ActionScheduler_QueueRunner;
use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionScheduler as SchedulerProxy;
use Automattic\WooCommerce\Pinterest\FeedFileOperations;
use Automattic\WooCommerce\Pinterest\FeedGenerator;
use Automattic\WooCommerce\Pinterest\Feeds;
use Automattic\WooCommerce\Pinterest\FeedRegistration;
use Automattic\WooCommerce\Pinterest\LocalFeedConfigs;
use Automattic\WooCommerce\Pinterest\ProductFeedStatus;
use Automattic\WooCommerce\Pinterest\ProductSync;
use Pinterest_For_Woocommerce as Plugin;

/**
 * Exercise real products, saved configurations, scheduler actions and files together.
 *
 * @version x.x.x
 */
class CatalogPublicationTest extends \WP_UnitTestCase {

	/** @var string Owned output directory. */
	private $directory;

	/** @var array Original static caches. */
	private $statics = array();

	/** @var int[] Action IDs that predate this case. */
	private $existing_actions;

	/** @var int Native batch bound for this case. */
	private $products_per_batch = 1;

	/** Prepare owned output, static caches and native configuration. */
	public function setUp(): void {
		parent::setUp();
		$this->directory = sys_get_temp_dir() . '/pinterest-catalog-' . wp_generate_uuid4();
		mkdir( $this->directory );
		foreach ( array(
			LocalFeedConfigs::class  => 'instance',
			ProductFeedStatus::class => 'state',
		) as $class => $name ) {
			$property = new \ReflectionProperty( $class, $name );
			if ( PHP_VERSION_ID < 80100 ) {
				$property->setAccessible( true );
			}
			$this->statics[] = array( $property, $property->getValue() );
			$property->setValue( null, 'instance' === $name ? null : array() );
		}
		$this->existing_actions = $this->action_ids();
		add_filter( 'upload_dir', array( $this, 'upload_directory' ) );
		add_filter( PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME . '_feed_product_batch_size', array( $this, 'batch_size' ) );
		update_option( 'woocommerce_default_country', 'US:CA' );
		update_option( 'woocommerce_currency', 'USD' );
		update_option( 'woocommerce_calc_taxes', 'no' );
		ProductFeedStatus::set( ProductFeedStatus::STATE_PROPS );
	}

	/** Restore fixture state even when a test fails. */
	public function tearDown(): void {
		try {
			foreach ( array_diff( $this->action_ids(), $this->existing_actions ) as $id ) {
				ActionScheduler::store()->delete_action( $id );
			}
			$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->directory, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
			foreach ( $files as $file ) {
				if ( $file->isDir() ) {
					rmdir( $file->getPathname() );
				} else {
					unlink( $file->getPathname() );
				}
			}
			rmdir( $this->directory );
		} finally {
			foreach ( $this->statics as list( $property, $value ) ) {
				$property->setValue( null, $value );
			}
			parent::tearDown();
		}
	}

	/**
	 * Keep native output in the owned directory.
	 *
	 * @param array $uploads Native upload settings.
	 * @return array
	 */
	public function upload_directory( $uploads ) {
		$uploads['basedir'] = $this->directory;
		$uploads['baseurl'] = 'http://example.org/catalog-tests';
		return $uploads;
	}

	/**
	 * Force multiple native batches.
	 *
	 * @param int $size Default batch size.
	 * @return int
	 */
	public function batch_size( $size ) {
		return $this->products_per_batch;
	}

	/** @return array Saved configuration and failure scenarios. */
	public function publication_cases() {
		return array(
			'normal base country'                 => array( false ),
			'two saved countries, failed restart' => array( true ),
		);
	}

	/**
	 * Partial output cannot replace a usable publication or escape the registration gate.
	 *
	 * @dataProvider publication_cases
	 * @param bool $fail_write Use two saved configurations and interrupt a later write.
	 */
	public function test_scheduled_catalog_publication_and_retry( $fail_write ) {
		$http_calls = array();
		$block_http = function ( $response, $args, $url ) use ( &$http_calls ) {
			$http_calls[] = array( $args['method'], wp_parse_url( $url, PHP_URL_HOST ), wp_parse_url( $url, PHP_URL_PATH ) );
			return new \WP_Error( 'catalog_test_no_network', 'Catalog fixture must not contact remote services.' );
		};
		add_filter( 'pre_http_request', $block_http, 10, 3 );
		$expected = array();
		foreach ( array( '10.00', '12.50', '3.75' ) as $price ) {
			$product = new \WC_Product_Simple();
			$product->set_name( 'Catalog product ' . $price );
			$product->set_status( 'publish' );
			$product->set_regular_price( $price );
			$product->set_virtual( true );
			$product->set_tax_status( 'none' );
			$expected[] = array( (string) $product->save(), $price . 'USD' );
		}
		$draft = new \WC_Product_Simple();
		$draft->set_status( 'draft' );
		$draft->set_regular_price( '99' );
		$draft->save();
		Plugin::save_data(
			'local_feed_ids',
			$fail_write ? array(
				'US' => 'saved-us',
				'CA' => 'saved-ca',
			) : array()
		);
		Plugin::save_setting( 'tracking_advertiser', 'catalog-test' );
		Feeds::invalidate_feeds_cache();
		$configurations = LocalFeedConfigs::get_instance();
		$configs        = $configurations->get_configurations();
		// The default initializer may discover a previous remote feed; this controlled lookup cannot leave the test.
		$this->assertSame( $fail_write ? array() : array( array( 'GET', 'api.pinterest.com', '/v5/catalogs/feeds' ) ), $http_calls );
		$http_calls = array();
		$this->assertSame( $fail_write ? array( 'US', 'CA' ) : array( 'US' ), array_keys( $configs ) );
		$operations   = new FeedFileOperations( $configurations );
		$registration = new FeedRegistration( $configurations, $operations );
		$generator    = new FeedGenerator( new SchedulerProxy(), $operations, $configurations );
		$generator->init();
		$previous = '<rss xmlns:g="http://base.google.com/ns/1.0"><channel><item><g:id>previous</g:id><g:price>8.00USD</g:price></item></channel></rss>';
		foreach ( $configs as $config ) {
			file_put_contents( $config['tmp_file'], 'Stale output from a previous attempt' );
			if ( $fail_write ) {
				file_put_contents( $config['feed_file'], $previous );
			}
		}

		$starts = as_get_scheduled_actions(
			array(
				'hook'   => FeedGenerator::ACTION_START_FEED_GENERATOR,
				'status' => 'pending',
				'group'  => PINTEREST_FOR_WOOCOMMERCE_PREFIX,
			),
			'ids'
		);
		$this->assertCount( 1, $starts );
		ActionScheduler_QueueRunner::instance()->process_action( $starts[0] );
		$this->run_next( 'chain_start' );
		$this->run_next( 'chain_batch' );
		$this->assertSame( 1, ProductFeedStatus::get()['product_count'] );
		$this->assertSame( 'in_progress', ProductFeedStatus::get()['status'] );
		foreach ( $configs as $config ) {
			$this->assertStringContainsString( '<g:id>' . $expected[0][0] . '</g:id>', file_get_contents( $config['tmp_file'] ) );
			if ( $fail_write ) {
				$this->assertSame( $previous, file_get_contents( $config['feed_file'] ) );
			} else {
				$this->assertFileDoesNotExist( $config['feed_file'] );
			}
		}
		if ( $fail_write ) {
			// US appends successfully; the second country's native write fails on a directory.
			$path = $configs['CA']['tmp_file'];
			rename( $path, $path . '.backup' );
			mkdir( $path );
			// phpcs:disable WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting -- Expected native I/O warnings only; restored in finally.
			$level = error_reporting();
			try {
				error_reporting( $level & ~E_WARNING );
				$this->run_next( 'chain_batch', 'failed' );
			} finally {
				error_reporting( $level );
				rmdir( $path );
				unlink( $path . '.backup' );
			}
			// phpcs:enable WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
			$this->assertSame( 'error', ProductFeedStatus::get()['status'] );
			$this->assertSame( 1, ProductFeedStatus::get()['product_count'] );
			$this->assertStringContainsString( 'Could not open temporary file', ProductFeedStatus::get()['error_message'] );
			foreach ( $configs as $country => $config ) {
				$this->assertSame( $previous, file_get_contents( $config['feed_file'] ) );
				$this->assertSame( array( array( 'previous', '8.00USD' ) ), $this->xml_items( $config['feed_file'] ) );
				// CA's directory was a test obstacle; US is the real appended partial file the plugin must remove.
				if ( 'US' === $country ) {
					$this->assertFileDoesNotExist( $config['tmp_file'] );
				}
			}
			$retry = as_next_scheduled_action( FeedGenerator::ACTION_START_FEED_GENERATOR, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
			$this->assertGreaterThanOrEqual( time() + HOUR_IN_SECONDS - 5, $retry );
			// Execute the native scheduled retry now; the queue runner accepts a selected future action.
			$ids = as_get_scheduled_actions(
				array(
					'hook'   => FeedGenerator::ACTION_START_FEED_GENERATOR,
					'group'  => PINTEREST_FOR_WOOCOMMERCE_PREFIX,
					'status' => 'pending',
				),
				'ids'
			);
			$this->assertCount( 1, $ids );
			ActionScheduler_QueueRunner::instance()->process_action( $ids[0] );
			$this->run_next( 'chain_start' );
		}
		for ( $batch = 0; $batch < 4; ++$batch ) {
			if ( ! as_has_scheduled_action( 'pinterest/jobs/generate_feed/chain_batch', null, PINTEREST_FOR_WOOCOMMERCE_PREFIX ) ) {
				break;
			}
			$this->run_next( 'chain_batch' );
			$this->assertSame( 'in_progress', ProductFeedStatus::get()['status'] );
			if ( ! $fail_write ) {
				$this->assertTrue( $registration->handle_feed_registration() );
				$this->assertFalse( $registration->feed_file_exists() );
			}
			foreach ( $configs as $config ) {
				if ( $fail_write ) {
					$this->assertSame( $previous, file_get_contents( $config['feed_file'] ) );
				} else {
					$this->assertFileDoesNotExist( $config['feed_file'] );
				}
			}
		}
		$this->assertFalse( as_has_scheduled_action( 'pinterest/jobs/generate_feed/chain_batch', null, PINTEREST_FOR_WOOCOMMERCE_PREFIX ) );
		$this->assertSame( array(), $http_calls );
		$this->run_next( 'chain_end' );
		$this->assertSame( 'generated', ProductFeedStatus::get()['status'] );
		$this->assertSame( 3, ProductFeedStatus::get()['product_count'] );
		$this->assertSame( 3, ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT ] );
		$this->assertTrue( $registration->feed_file_exists() );
		foreach ( $configs as $config ) {
			$this->assertSame( $expected, $this->xml_items( $config['feed_file'] ) );
			$this->assertFileDoesNotExist( $config['tmp_file'] );
		}
	}

	/**
	 * Permanently deleting a published product or variation marks the feed dirty; excluded posts do not.
	 */
	public function test_permanent_deletion_marks_feed_dirty() {
		Plugin::save_settings(
			array(
				'product_sync_enabled' => true,
				'account_data'         => array( 'verified_user_websites' => array( wp_parse_url( home_url(), PHP_URL_HOST ) ) ),
			)
		);
		foreach ( array( 'feed_generator', 'feed_registration', 'configurations', 'flagged_product_ids' ) as $name ) {
			$property = new \ReflectionProperty( ProductSync::class, $name );
			if ( PHP_VERSION_ID < 80100 ) {
				$property->setAccessible( true );
			}
			$this->statics[] = array( $property, $property->getValue() );
			$property->setValue( null, 'flagged_product_ids' === $name ? array() : null );
		}
		$http = static function () {
			return new \WP_Error( 'deletion_no_network', 'Deletion fixtures cannot contact remote services.' );
		};
		add_filter( 'pre_http_request', $http );
		try {
			ProductSync::maybe_init();

			$draft = new \WC_Product_Simple();
			$draft->set_status( 'draft' );
			$draft->save();
			$published = new \WC_Product_Simple();
			$published->set_status( 'publish' );
			$published->set_regular_price( '10' );
			$published->save();
			$parent = new \WC_Product_Variable();
			$parent->set_status( 'publish' );
			$parent->save();
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
			$variation->set_status( 'publish' );
			$variation->set_regular_price( '15' );
			$variation->save();

			update_option( FeedGenerator::OPTION_FEED_DIRTY, 0, false );
			$draft->delete( true );
			$this->assertSame( 0, (int) get_option( FeedGenerator::OPTION_FEED_DIRTY ), 'Deleting a draft cannot change the feed.' );

			foreach ( array( $published, $variation ) as $product ) {
				update_option( FeedGenerator::OPTION_FEED_DIRTY, 0, false );
				$product->delete( true );
				$this->assertSame( 1, (int) get_option( FeedGenerator::OPTION_FEED_DIRTY ), $product->get_type() . ' deletion must regenerate the feed.' );
			}
		} finally {
			remove_filter( 'pre_http_request', $http );
		}
	}

	/**
	 * Run one action and verify its actual scheduler disposition.
	 *
	 * @param string $step Native chain step.
	 * @param string $status Expected scheduler status.
	 */
	private function run_next( $step, $status = 'complete' ) {
		$ids = as_get_scheduled_actions(
			array(
				'hook'   => 'pinterest/jobs/generate_feed/' . $step,
				'status' => 'pending',
				'group'  => PINTEREST_FOR_WOOCOMMERCE_PREFIX,
			),
			'ids'
		);
		$this->assertCount( 1, $ids, $step );
		ActionScheduler_QueueRunner::instance()->process_action( $ids[0] );
		$this->assertSame( $status, ActionScheduler::store()->get_status( $ids[0] ) );
	}

	/**
	 * Parse exact IDs and money, including duplicate rows and ordering.
	 *
	 * @param string $file Native feed file.
	 * @return array
	 */
	private function xml_items( $file ) {
		$xml = simplexml_load_file( $file );
		$this->assertInstanceOf( \SimpleXMLElement::class, $xml );
		$items = array();
		foreach ( $xml->channel->item as $item ) {
			$fields  = $item->children( 'g', true );
			$items[] = array( (string) $fields->id, (string) $fields->price );
		}
		sort( $items );
		return $items;
	}

	/** @return int[] Scheduler actions in this plugin's group. */
	private function action_ids() {
		return as_get_scheduled_actions(
			array(
				'group'    => PINTEREST_FOR_WOOCOMMERCE_PREFIX,
				'per_page' => -1,
			),
			'ids'
		);
	}
}
