<?php
/**
 * Plugin update procedures
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @since       x.x.x
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class for performing plugin update procedures.
 */
class PluginUpdate {

	/**
	 * Perform plugin update procedures.
	 *
	 * @param string $old_version Plugin version from which we start the update.
	 * @return void
	 */
	public static function update( $old_version ) {

		if ( version_compare( '2.0.0', $old_version, '>' ) ) {
			self::update_to_2_0_0();
		}
	}

	/**
	 * Update plugin to the version 2.0.0
	 *
	 * @return void
	 */
	public static function update_to_2_0_0() {
		/*
		 * 1. Cancel old actions.
		 */
		as_unschedule_all_actions( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-handle-sync', array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		as_unschedule_all_actions( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-feed-generation', array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );

		/*
		 * 2. Move feed file to a new location.
		 */
		$feed_id          = Pinterest_For_Woocommerce()::get_data( 'local_feed_id' );
		$default_location = Pinterest_For_Woocommerce()::get_base_country() ?? 'US';
		if ( $feed_id ) {
			// Generate new configurations.
			$new_configs = ( new LocalFeedConfigs( array( $default_location ) ) )->get_configurations();
			// We only migrate the default location, other configs do not exist at this stage.
			$new_config = $new_configs[ $default_location ];

			$upload_dir = wp_get_upload_dir();
			$old_config = array(
				'feed_file' => trailingslashit( $upload_dir['basedir'] ) . PINTEREST_FOR_WOOCOMMERCE_LOG_PREFIX . '-' . $feed_id . '.xml',
				'tmp_file'  => trailingslashit( $upload_dir['basedir'] ) . PINTEREST_FOR_WOOCOMMERCE_LOG_PREFIX . '-' . $feed_id . '-tmp.xml',
			);

			if ( file_exists( $old_config['feed_file'] ) ) {
				rename( $old_config['feed_file'], $new_config['feed_file'] );
			}
			if ( file_exists( $old_config['tmp_file'] ) ) {
				unlink( $old_config['feed_file'] );
			}

			/*
			 * 2-a Next call to FeedRegistration::register_feed() will handle the url change. So we can skip this step and rely on automation.
			 */

			/*
			 * 2-b. Move state.
			 */
			$data_prefix = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_' . $feed_id . '_';

			$old_state = array(
				'status'        => get_transient( $data_prefix . 'status' ),
				'current_index' => get_transient( $data_prefix . 'current_index' ),
				'last_activity' => get_transient( $data_prefix . 'last_activity' ),
				'product_count' => get_transient( $data_prefix . 'product_count' ),
				'error_message' => get_transient( $data_prefix . 'error_message' ),
			);

			foreach ( $old_state as $key => $value ) {
				delete_transient( $data_prefix . $key );
			}

			unset( $old_state['product_count'] );

			ProductFeedStatus::set( $old_state );

			/*
			 * 2-c. PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_dataset_' transient will be removed after WEEK_IN_SECONDS.
			 */
		}

		/*
		 * 3. Clear data.
		 */
		$settings = Pinterest_For_Woocommerce()::get_settings( true, PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );

		unset( $settings['local_feed_id'] );

		Pinterest_For_Woocommerce()::save_settings( $settings, PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );

		// Update done.
	}
}
