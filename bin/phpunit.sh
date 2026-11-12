#!/usr/bin/env bash

# PHPUnit runner for the wp-env tests site (.wp-env.test.json).
#
# This script should only run locally. CI installs the test suite through
# bin/install-wp-tests.sh (see .github/workflows/run-unit-tests.yml).
#
# USAGE
#   npm run env:test:unit [-- phpunit args]   Run PHPUnit; installs WP + WooCommerce on first run
#   npm run env:test:unit:setup               Reinstall WP + WooCommerce (e.g. after changing a version)
#   npm run env:test:unit:help                Show this message
#
# OPTIONS (env vars)
#   WC_ORDER_DATASTORE=hpos   Order storage: cpt (default) or hpos; CI runs both
#   UNIT_WC_VERSION=10.3.0    WooCommerce: latest (default, as CI) or X.Y.Z; trunk also means latest  (setup only)
#   UNIT_WP_VERSION=7.0       WordPress:   latest (default, as CI), nightly, or X.Y[.Z] (setup only)
#
# EXAMPLES
#   npm run env:test:unit -- --filter <TestClassName>
#   WC_ORDER_DATASTORE=hpos npm run env:test:unit
#   UNIT_WC_VERSION=10.3.0 npm run env:test:unit:setup && npm run env:test:unit
#
# Worktrees: export WP_ENV_PORT / WP_ENV_TESTS_PORT first (see AGENTS.md).
set -euo pipefail

cd "$(dirname "$0")/.."

TESTS_DIR=/tmp/wordpress-tests-lib
# .wp-env.test.json maps the checkout to the plugin slug, so this also works in worktrees.
PLUGIN_DIR=wp-content/plugins/pinterest-for-woocommerce

usage() {
	sed -n '/^# USAGE/,/^set -euo/p' "$0" | sed '$d; s/^# \{0,1\}//'
}

compose() {
	local install_path
	install_path=$(npm run --silent wp-env:test -- status --json | node -p 'JSON.parse(require("fs").readFileSync(0, "utf8")).installPath')
	docker compose -f "${install_path}/docker-compose.yml" "$@"
}

# Same as `npm run wp-env:test -- run cli --env-cwd=<dir> <command>` (host user, so files it writes stay
# ours), minus wp-env's argument parser, which turns `7.0` into `7` and keeps `--debug`,
# `--help` and `--version` for itself.
exec_tests_cli() {
	compose exec -u "$(id -u):$(id -g)" -w "$1" cli "${@:2}"
}

setup() {
	compose exec -T -u root cli apk add --no-cache subversion git
	# Run from /tmp: in a git worktree, the checkout's .git points to a host path that
	# doesn't exist in the container, which makes install.sh's `git ls-remote` fail.
	# install-wp-tests.sh keeps what it finds and asks before replacing the database, so start clean.
	exec_tests_cli /tmp rm -rf /tmp/wordpress "$TESTS_DIR"
	exec_tests_cli /tmp mysql --user=root --password=password --host=mysql --protocol=tcp -e 'DROP DATABASE IF EXISTS wordpress_test'
	exec_tests_cli /tmp \
		env WP_TESTS_DIR="$TESTS_DIR" WC_VERSION="${UNIT_WC_VERSION:-latest}" bash "/var/www/html/${PLUGIN_DIR}/bin/install-wp-tests.sh" \
		wordpress_test root password mysql "${UNIT_WP_VERSION:-latest}"
}

run() {
	if [ -z "$(compose ps -q --status running cli)" ]; then
		echo "wp-env is not running. Start it with: npm run env:test:start (export WP_ENV_PORT / WP_ENV_TESTS_PORT first in a worktree)." >&2
		exit 1
	fi
	# install.sh empties each directory before refilling it, so any missing file means a failed or interrupted setup.
	if ! compose exec -T cli test -f /tmp/wordpress/wp-settings.php -a -f "${TESTS_DIR}/includes/functions.php" -a -f /tmp/wordpress/wp-content/plugins/woocommerce/woocommerce.php; then
		echo "Test suite missing or incomplete in the tests site; running setup first."
		setup
	fi
	exec_tests_cli "/var/www/html/${PLUGIN_DIR}" \
		env WP_TESTS_DIR="$TESTS_DIR" WC_ORDER_DATASTORE="${WC_ORDER_DATASTORE:-cpt}" \
		php vendor/bin/phpunit -c phpunit.xml.dist --fail-on-empty-test-suite "$@"
}

case "${1:-run}" in
	setup) setup ;;
	run) shift || true; run "$@" ;;
	help | -h | --help) usage ;;
	*) usage; exit 1 ;;
esac
