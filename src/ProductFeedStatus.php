
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
 *
 */
class ProductFeedStatus {


	private static $local_feed = array();

	/**
	 * Saves or returns the Current state of the Feed generation job.
	 * Status can be one of the following:
	 *
	 * - starting                 The feed job is being initialized. A new JobID will be assigned if none exists.
	 * - check_registration       If a JobID already exists, it is returned, otherwise a new one will be assigned. // split into a new method.
	 * - in_progress              Signifies that we are between iterations and generating the feed.
	 * - generated                The feed is generated, no further action will be taken.
	 * - scheduled_for_generation The feed needs to be (re)generated. If this status is set, the next run of __CLASS__::handle_feed_generation() will start the generation process.
	 * - pending_config           The feed was reset or was never configured.
	 * - error                    The generation process returned an error.
	 *
	 * @param array  $args   The arguments that go along with the given status.
	 *
	 * @return array
	 */
	public static function get( $props = array() ) {

		$local_feed  = self::get_local_feed();
		$data_prefix = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_' . $local_feed['feed_id'] . '_';
		$status      = array();
		$props       = is_array( $props ) ? $props : array( $props );

		$all_props = array(
			'status'        => 'pending_config',
			'current_index' => false,
			// 'progress'      => 0,
			'error_message' => '',
			'last_activity' => 0,
			'product_count' => 0,
		);

		$props = ! empty( $props ) ? array_intersect_key( $all_props, array_flip( $props ) ) : $all_props;

		foreach ( $props as $key => $default_value ) {
			$stored         = get_transient( $data_prefix . $key );
			$status[ $key ] = false === $stored ? $default_value : $stored;
		}

		return $status;
	}

	/**
	 * Undocumented function
	 *
	 * status
	 * current_index
	 *     //  * progress ???
	 * error_message
	 * last_activity
	 *
	 * @param array $state
	 * @return void
	 */
	public static function set( $state ) {

		$local_feed  = self::get_local_feed();
		$data_prefix = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_' . $local_feed['feed_id'] . '_';

		$state['last_activity'] = time();

		if ( 'starting' === $state['status'] ) {
			$state['started']  = time();
		}

		foreach ( $state as $key => $value ) {
			set_transient( $data_prefix . $key, $value ); // No expiration.
		}

		if ( ! empty( $state['status'] ) ) {
			do_action( 'pinterest_for_woocommerce_feed_' . $state['status'], $state );
		}
	}


	public static function store_dataset( $dataset ) {

		$local_feed = self::get_local_feed();

		return set_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_dataset_' . $local_feed['feed_id'], $dataset, WEEK_IN_SECONDS );
	}

	public static function retrieve_dataset() {

		$local_feed = self::get_local_feed();

		return get_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_dataset_' . $local_feed['feed_id'] );
	}


	public static function feed_data_cleanup() {

		$local_feed = self::get_local_feed();

		delete_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_dataset_' . $local_feed['feed_id'] );
	}



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


	public static function get_local_feed() {

		if ( empty( self::$local_feed ) ) {
			self::init_local_feed();
		}

		return self::$local_feed;
	}
}