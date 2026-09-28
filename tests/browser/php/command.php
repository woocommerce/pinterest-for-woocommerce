<?php
/**
 * Named browser-store operations, invoked through WP-CLI eval-file.
 *
 * @package Pinterest_For_WooCommerce
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'PINTEREST_E2E' ) || ! PINTEREST_E2E ) {
	throw new RuntimeException( 'Browser fixtures require WP-CLI and the PINTEREST_E2E test store.' );
}

require_once __DIR__ . '/StoreFixture.php';

$fixture_options = json_decode( $args[1] ?? '{}' );
if ( ! is_object( $fixture_options ) || JSON_ERROR_NONE !== json_last_error() ) {
	WP_CLI::error( 'Fixture options must be a JSON object.' );
}

$fixture_result = ( new Automattic\WooCommerce\Pinterest\Tests\Browser\StoreFixture() )->run(
	$args[0] ?? '',
	(array) $fixture_options
);
WP_CLI::line( 'PINTEREST_RESULT:' . wp_json_encode( $fixture_result ) );
