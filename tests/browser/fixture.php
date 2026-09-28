<?php
/**
 * MU bootstrap for the dedicated Pinterest browser store.
 *
 * @package Pinterest_For_WooCommerce
 */

if ( ! defined( 'PINTEREST_E2E' ) || ! PINTEREST_E2E ) {
	return;
}

/**
 * Model the WP Consent API's public function using the browser test cookie.
 *
 * @param string $category Consent category.
 * @return bool
 */
function wp_has_consent( $category ) {
	return 'marketing' !== $category || ( isset( $_COOKIE['pinterest_e2e_consent'] ) && 'allow' === $_COOKIE['pinterest_e2e_consent'] );
}

require_once WP_PLUGIN_DIR . '/pinterest-for-woocommerce/tests/browser/php/HttpFixture.php';
( new Automattic\WooCommerce\Pinterest\Tests\Browser\HttpFixture() )->register();
