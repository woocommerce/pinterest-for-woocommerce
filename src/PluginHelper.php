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

	/**
	 * Helper method to return the onboarding page parameters.
	 *
	 * @return array The onboarding page parameters.
	 */
	protected function onboarding_page_parameters(): array {

		return array(
			'page' => 'wc-admin',
			'path' => '/pinterest/onboarding',
		);
	}

	/**
	 * Check wether if the current page is the Get Started page.
	 *
	 * @return bool Wether the current page is the Get Started page.
	 */
	protected function is_onboarding_page(): bool {

		$page_parameters = $this->onboarding_page_parameters();

		return count( $page_parameters ) === count( array_intersect_assoc( $_GET, $page_parameters ) ); // phpcs:disable WordPress.Security.NonceVerification.Recommended
	}

}
