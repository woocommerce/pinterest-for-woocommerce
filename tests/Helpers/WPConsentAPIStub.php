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
 * Returns the value unchanged because the real function is filterable and is
 * not guaranteed to return a boolean.
 *
 * @param string $category Consent category.
 * @return mixed
 */
function wp_has_consent( $category ) {
	return 'marketing' === $category ? ( $GLOBALS['pinterest_test_marketing_consent'] ?? null ) : true;
}
