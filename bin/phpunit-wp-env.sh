#!/usr/bin/env bash
#
# Run PHPUnit inside wp-env's tests-cli container with the env vars
# tests/bootstrap.php expects.
#
# Invoked from package.json scripts:
#     "test:php:wp-env": "wp-env run tests-cli \"bash /var/www/html/.../bin/phpunit-wp-env.sh\""
#
# Extra args are forwarded to phpunit:
#     npm run test:php:wp-env -- --filter MyTest

set -eu

export WP_TESTS_DIR="${WP_TESTS_DIR:-/wordpress-phpunit}"
export WP_CORE_DIR="${WP_CORE_DIR:-/var/www/html}"

cd "${PINTEREST_FOR_WOOCOMMERCE_DIR:-/var/www/html/wp-content/plugins/pinterest-for-woocommerce}"

exec vendor/bin/phpunit "$@"
