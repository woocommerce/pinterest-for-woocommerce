<?php
/**
 * Namespaced stand-in for the WP Consent API's wp_has_consent().
 *
 * Unqualified calls from the plugin namespace resolve here before the global
 * function, which stays undefined, so WPConsentAPI still reports the API as
 * inactive and registers no filters in other tests.
 *
 * @package Pinterest_For_WooCommerce/Tests
 */

namespace Automattic\WooCommerce\Pinterest;

/**
 * Reports marketing consent from $GLOBALS['pinterest_test_marketing_consent'].
 *
 * @param string $category Consent category.
 * @return bool
 */
function wp_has_consent( $category ) {
	return 'marketing' !== $category || ! empty( $GLOBALS['pinterest_test_marketing_consent'] );
}
