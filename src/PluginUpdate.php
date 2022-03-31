<?php
/**
 * Helper class for performing various update procedures.
 *
 * @package Automattic\WooCommerce\Pinterest
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use Exception;
use Throwable;
/**
 * Class PluginUpdate
 *
 * 1. Check if the plugin is up to date. If yes return immediately.
 * 2. Perform update procedures.
 * 3. Bump update version string.
 */
class PluginUpdate {

	/**
	 * Option name used for storing version of the plugin before the update procedure.
	 */
	const PLUGIN_UPDATE_VERSION_OPTION = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-update-version';

	/**
	 * Check if the plugin is up to date.
	 *
	 * @since 1.0.9
	 * @return boolean
	 */
	public function plugin_is_up_to_date(): bool {
		return version_compare(
			$this->get_plugin_update_version(),
			$this->get_plugin_current_version(),
			'=='
		);
	}

	/**
	 * Helper function to check if update to $version is needed.
	 *
	 * @since 1.0.9
	 * @param string $version Version string for which we check if update is needed.
	 * @return boolean
	 */
	private function version_needs_update( string $version ): bool {
		return version_compare(
			$this->get_plugin_update_version(),
			$version,
			'<'
		);
	}

	/**
	 * Gets the previous version of the plugin. The one before the update has
	 * happened. After the update procedure this will return the same version
	 * as get_plugin_current_version().
	 *
	 * @since 1.0.9
	 * @return string
	 */
	private function get_plugin_update_version(): string {
		return get_option( self::PLUGIN_UPDATE_VERSION_OPTION, '1.0.0' );
	}

	/**
	 * Returns the version of the plugin as defined in the main plugin file.
	 *
	 * @since 1.0.9
	 * @return string
	 */
	private function get_plugin_current_version(): string {
		return PINTEREST_FOR_WOOCOMMERCE_VERSION;
	}

	/**
	 * After the update has been completed bump the previous version option to
	 * the current version option.
	 *
	 * @since 1.0.9
	 * @return void
	 */
	public function update_plugin_update_version_option(): void {
		update_option(
			self::PLUGIN_UPDATE_VERSION_OPTION,
			$this->get_plugin_current_version()
		);
	}

	/**
	 * List of update procedures
	 *
	 * @since x.x.x
	 * @return array List of update procedures names.
	 */
	private function update_procedures() {
		return array(
			'domain_verification_migration',
			'feed_generation_migration',
		);
	}

	/**
	 * Update procedures entry point.
	 *
	 * @since 1.0.9
	 * @return void
	 */
	public function maybe_update(): void {

		// Return if the plugin is up to date.
		if ( $this->plugin_is_up_to_date() ) {
			return;
		}

		// Run the update procedures.
		foreach ( $this->update_procedures() as $update_procedure ) {
			$this->perform_plugin_update_procedure( $update_procedure );
		}

		/**
		 * Even if the update procedure has errored we still want to
		 * update the update version. This avoids problems where the
		 * update procedure will be called again and again. Update
		 * problems will need to be fixed in the next patch release
		 * in this case.
		 */
		$this->update_plugin_update_version_option();

		Logger::log(
			sprintf(
				// translators: plugin version.
				__( 'Plugin updated to version: %s.', 'pinterest-for-woocommerce' ),
				$this->get_plugin_current_version()
			)
		);
	}

	/**
	 * Perform update procedure.
	 *
	 * @since 1.0.9
	 * @since x.x.x Accepts procedure name as parameter.
	 * @param  string $update_procedure Name of the migration procedure.
	 * @throws Throwable Update procedure failures.
	 * @return void
	 */
	protected function perform_plugin_update_procedure( $update_procedure ): void {
		try {
			$this->{$update_procedure}();
		} catch ( Throwable $th ) {
			Logger::log(
				sprintf(
					// translators: 1: plugin version, 2: error message.
					__( 'Plugin update to version %1$s error: %2$s', 'pinterest-for-woocommerce' ),
					$this->get_plugin_current_version(),
					$th->getMessage()
				),
				'error',
				null,
				true
			);
		}
	}

	/**
	 * Update procedure for the 1.0.9 version of the plugin.
	 *
	 * @since 1.0.9
	 * @throws Exception Verification error.
	 * @return void
	 */
	protected function domain_verification_migration(): void {
		if ( ! $this->version_needs_update( '1.0.9' ) ) {
			// Already up to date.
			return;
		}
		$account_data = Pinterest_For_Woocommerce()::get_setting( 'account_data' );

		/**
		 * Trigger update only if the user has performed verification
		 * procedure already. Otherwise we rely on the setup wizard process.
		 */
		if ( ! ( isset( $account_data['domain_verified'] ) && isset( $account_data['verified_domains'] ) ) ) {
			return;
		}

		$response = API\DomainVerification::trigger_domain_verification();
		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}
	}

	/**
	 * New style feed generator migration procedure.
	 *
	 * @return void
	 */
	protected function feed_generation_migration(): void {
		if ( ! $this->version_needs_update( '1.0.10' ) ) {
			// Already up to date.
			return;
		}

		/*
		 * 1. Cancel old actions.
		 */
		as_unschedule_all_actions( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-handle-sync', array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		as_unschedule_all_actions( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-feed-generation', array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );

		/*
		 * 2. Move feed file id to a new location.
		 */
		$feed_id = Pinterest_For_Woocommerce()::get_data( 'local_feed_id' );
		if ( null !== $feed_id ) {
			/*
			 * 2-a. Move location id to array of ids.
			 */
			$feed_ids = array(
				Pinterest_For_Woocommerce()::get_base_country() ?? 'US' => $feed_id,
			);
			Pinterest_For_Woocommerce()::save_data( 'local_feed_ids', $feed_ids );

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
