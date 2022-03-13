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
	 * @since x.x.x
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
	 * @since x.x.x
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
	 * @since x.x.x
	 * @return string
	 */
	private function get_plugin_update_version(): string {
		return get_option( self::PLUGIN_UPDATE_VERSION_OPTION, '1.0.0' );
	}

	/**
	 * Returns the version of the plugin as defined in the main plugin file.
	 *
	 * @since x.x.x
	 * @return string
	 */
	private function get_plugin_current_version(): string {
		return PINTEREST_FOR_WOOCOMMERCE_VERSION;
	}

	/**
	 * After the update has been completed bump the previous version option to
	 * the current version option.
	 *
	 * @since x.x.x
	 * @return void
	 */
	private function update_plugin_update_version_option(): void {
		update_option(
			self::PLUGIN_UPDATE_VERSION_OPTION,
			$this->get_plugin_current_version()
		);

		Logger::log(
			sprintf(
				// translators: plugin version.
				__( 'Plugin updated to version: %s.', 'pinterest-for-woocommerce' ),
				$this->get_plugin_current_version()
			)
		);
	}

	/**
	 * Update procedures entry point.
	 *
	 * @since x.x.x
	 * @return void
	 */
	public function maybe_update(): void {

		// Return if the plugin is up to date.
		if ( $this->plugin_is_up_to_date() ) {
			return;
		}

		try {
			$this->perform_plugin_updates();
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

		/**
		 * Even if the update procedure has errored we still want to
		 * update the update version. This avoids problems where the
		 * update procedure will be called again and again. Update
		 * problems will need to be fixed in the next patch release
		 * in this case.
		 */
		$this->update_plugin_update_version_option();
	}

	/**
	 * Perform update procedures.
	 *
	 * @since x.x.x
	 * @throws Throwable Update procedure failures.
	 * @return void
	 */
	protected function perform_plugin_updates(): void {
		$this->update_to_1_0_9();
	}

	/**
	 * Update procedure for the 1.0.9 version of the plugin.
	 *
	 * @since x.x.x
	 * @return void
	 */
	private function update_to_1_0_9(): void {
		if ( ! $this->version_needs_update( '1.0.9' ) ) {
			return;
		}
	}

}
