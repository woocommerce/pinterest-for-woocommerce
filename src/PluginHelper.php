<?php
/**
 * Helper functions that are useful throughout the plugin.
 *
 * @package Automattic\WooCommerce\Pinterest
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest;

/**
 * Trait PluginHelper
 */
trait PluginHelper {

	/**
	 * Get the plugin slug.
	 *
	 * @return string
	 */
	protected function get_slug(): string {
		return 'pinterest';
	}

	/**
	 * Get the prefix used for plugin's metadata keys in the database.
	 *
	 * @return string
	 */
	protected function get_meta_key_prefix(): string {
		return "_wc_{$this->get_slug()}";
	}

	/**
	 * Prefix a meta data key with the plugin prefix.
	 *
	 * @param string $key Meta key name.
	 *
	 * @return string
	 */
	protected function prefix_meta_key( string $key ): string {
		$prefix = $this->get_meta_key_prefix();

		return "{$prefix}_{$key}";
	}

	/**
	 * Check whether debugging mode is enabled.
	 *
	 * @return bool Whether debugging mode is enabled.
	 */
	protected function is_debug_mode(): bool {
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

}
