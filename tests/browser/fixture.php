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
	$allowed = 'marketing' !== $category
		|| ( isset( $_COOKIE['pinterest_e2e_consent'] ) && 'allow' === $_COOKIE['pinterest_e2e_consent'] );
	// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- Native consent fixture.
	return apply_filters( 'wp_has_consent', $allowed, $category, null );
}

require_once WP_PLUGIN_DIR . '/pinterest-for-woocommerce/tests/browser/php/HttpFixture.php';
( new Automattic\WooCommerce\Pinterest\Tests\Browser\HttpFixture() )->register();

// Model the real consent API's footer timing only for the dedicated late-load case.
add_action(
	'wp_footer',
	function () {
		if ( isset( $_COOKIE['pinterest_e2e_late_consent'] ) ) {
			wp_print_inline_script_tag( 'window.wp_has_consent=function(category){return category==="marketing";};' );
		}
	},
	20
);

// Register a customized PHP profile only in its dedicated compatibility cases.
if ( isset( $_COOKIE['pinterest_e2e_force_tracking'] ) ) {
	switch ( $_COOKIE['pinterest_e2e_force_tracking'] ) {
		case 'global':
			add_filter( 'woocommerce_pinterest_disable_tracking', '__return_false' );
			break;
		case 'user-consent':
			add_filter( 'woocommerce_pinterest_disable_tracking_user_consent', '__return_false', 20 );
			break;
		case 'api':
			add_filter( 'wp_has_consent', '__return_true' );
			break;
	}
}
