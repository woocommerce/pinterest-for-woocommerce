<?php
/**
 * Declares runtime-defined constants for PHPStan.
 *
 * PHPStan does not see constants defined inside conditional blocks or
 * functions, or by other plugins. Values are placeholders of the runtime
 * type. The plugin never loads this file.
 *
 * @package woocommerce/pinterest-for-woocommerce
 */

// Defined in class-pinterest-for-woocommerce.php.
define( 'PINTEREST_FOR_WOOCOMMERCE_PREFIX', 'pinterest-for-woocommerce' );
define( 'PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME', 'pinterest-for-woocommerce/pinterest-for-woocommerce.php' );
define( 'PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME', 'pinterest_for_woocommerce' );
define( 'PINTEREST_FOR_WOOCOMMERCE_DATA_NAME', 'pinterest_for_woocommerce_data' );
define( 'PINTEREST_FOR_WOOCOMMERCE_LOG_PREFIX', 'pinterest-for-woocommerce' );
define( 'PINTEREST_FOR_WOOCOMMERCE_WOO_CONNECT_URL', 'https://api.woocommerce.com/' );
define( 'PINTEREST_FOR_WOOCOMMERCE_WOO_CONNECT_SERVICE', 'pinterest-v5' );
define( 'PINTEREST_FOR_WOOCOMMERCE_API_NAMESPACE', 'pinterest' );
define( 'PINTEREST_FOR_WOOCOMMERCE_CONNECT_NONCE', 'wp_rest' );
define( 'PINTEREST_FOR_WOOCOMMERCE_API_VERSION', '1' );
define( 'PINTEREST_FOR_WOOCOMMERCE_API_AUTH_ENDPOINT', 'oauth/callback' );
define( 'PINTEREST_FOR_WOOCOMMERCE_TRACKER_PREFIX', 'pfw' );
define( 'PINTEREST_FOR_WOOCOMMERCE_PINTEREST_API_VERSION', 'pinterest_for_woocommerce_pinterest_api_version' );

// Defined in wp-config.php.
define( 'AUTH_KEY', 'placeholder' );
