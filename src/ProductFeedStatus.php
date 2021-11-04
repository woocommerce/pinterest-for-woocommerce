<?php
/**
 * Pinterest for WooCommerce Rich Pins
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper methods that get/set the various feed state properties.
 */
class ProductFeedStatus {

	const STATE_PROPS = array(
		'status'        => 'pending_config',
		'current_index' => false,
		'last_activity' => 0,
		'product_count' => 0,
		'error_message' => '',
	);

	/**
	 * The array that holds the parameters of the feed
	 *
	 * @var array
	 */
	private static $local_feed = array();

	/**
	 * The array that holds the state of the feed, used as cache.
	 *
	 * @var array
	 */
	private static $state = array();

	/**
	 * Returns the Current state of the Feed generation job.
	 * Status can be one of the following:
	 *
	 * - starting                 The feed job is being initialized.
	 * - in_progress              Signifies that we are between iterations and generating the feed.
	 * - generated                The feed is generated, no further action is needed, unless the feed is expired.
	 * - scheduled_for_generation The feed is scheduled to be (re)generated. On this status, the next run of ProductSync::handle_feed_generation() will start the generation process.
	 * - pending_config           The feed was reset or was never configured.
	 * - error                    The generation process returned an error.
	 *
	 * @return array
	 */
	public static function get() {

		$local_feed  = self::get_local_feed();
		$data_prefix = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_' . $local_feed['feed_id'] . '_';

		foreach ( self::STATE_PROPS as $key => $default_value ) {

			if ( null === self::$state[ $key ] ) {
				self::$state[ $key ] = get_transient( $data_prefix . $key );
			}

			if ( false === self::$state[ $key ] ) {
				self::$state[ $key ] = $default_value;
			} elseif ( null === self::$state[ $key ] ) {
				self::$state[ $key ] = false;
			}
		}

		return self::$state;
	}

	/**
	 * Sets the Current state of the Feed generation job.
	 * See the docblock of self::get() for more info.
	 *
	 * @param array $state The array holding the feed state props to be saved.
	 * @return void
	 */
	public static function set( $state ) {

		$local_feed  = self::get_local_feed();
		$data_prefix = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_' . $local_feed['feed_id'] . '_';

		$state['last_activity'] = time();

		if ( 'starting' === $state['status'] ) {
			$state['started'] = time();
		}

		foreach ( $state as $key => $value ) {
			self::$state[ $key ] = $value;
			set_transient( $data_prefix . $key, ( false === $value ? null : $value ) ); // No expiration.
		}

		if ( ! empty( $state['status'] ) ) {
			do_action( 'pinterest_for_woocommerce_feed_' . $state['status'], $state );
		}
	}


	/**
	 * Stores the given dataset on a transient.
	 *
	 * @param array $dataset The product dataset to be saved.
	 *
	 * @return bool True if the value was set, false otherwise.
	 */
	public static function store_dataset( $dataset ) {

		$local_feed = self::get_local_feed();

		return set_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_dataset_' . $local_feed['feed_id'], $dataset, WEEK_IN_SECONDS );
	}

	/**
	 * Returns the stored dataset.
	 *
	 * @return mixed Value of transient.
	 */
	public static function retrieve_dataset() {

		$local_feed = self::get_local_feed();

		return get_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_dataset_' . $local_feed['feed_id'] );
	}


	/**
	 * Cleanup the stored dataset.
	 *
	 * *** TODO: hoook
	 *
	 * @return void
	 */
	public static function feed_data_cleanup() {

		$local_feed = self::get_local_feed();

		delete_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_dataset_' . $local_feed['feed_id'] );
	}


	/**
	 * Initialize the feed parameters based on the stored feed_id.
	 * If no feed_id is stored, create a new one.
	 *
	 * @return void
	 */
	private static function init_local_feed() {

		$feed_id = Pinterest_For_Woocommerce()::get_data( 'local_feed_id' );

		if ( ! $feed_id ) {
			$feed_id = wp_generate_password( 6, false, false );
			Pinterest_For_Woocommerce()::save_data( 'local_feed_id', $feed_id );
		}

		$upload_dir = wp_get_upload_dir();

		// Generate on the fly. That way the path/Urls follow the current site location.
		self::$local_feed = array(
			'feed_id'   => $feed_id,
			'feed_file' => trailingslashit( $upload_dir['basedir'] ) . PINTEREST_FOR_WOOCOMMERCE_LOG_PREFIX . '-' . $feed_id . '.xml',
			'tmp_file'  => trailingslashit( $upload_dir['basedir'] ) . PINTEREST_FOR_WOOCOMMERCE_LOG_PREFIX . '-' . $feed_id . '-tmp.xml',
			'feed_url'  => trailingslashit( $upload_dir['baseurl'] ) . PINTEREST_FOR_WOOCOMMERCE_LOG_PREFIX . '-' . $feed_id . '.xml',
		);
	}


	/**
	 * Return the array holding the local feed properties.
	 *
	 * @return array
	 */
	public static function get_local_feed() {

		if ( empty( self::$local_feed ) ) {
			self::init_local_feed();
		}

		return self::$local_feed;
	}


	/**
	 * Removes all transients for the given feed_id.
	 *
	 * @param string $feed_id The ID of the feed to cleanup transients for.
	 *
	 * @return void
	 */
	public static function feed_transients_cleanup( $feed_id ) {

		$data_prefix = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_' . $feed_id . '_';

		foreach ( self::STATE_PROPS as $key => $default_value ) {
			delete_transient( $data_prefix . $key );
		}

		delete_transient( 'pinterest-for-woocommerce_feed_dataset_' . $feed_id );
	}
}
