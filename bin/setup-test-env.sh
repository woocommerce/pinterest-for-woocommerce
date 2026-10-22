#!/usr/bin/env bash
# setup-test-env.sh
#
# Bootstrap the local PHPUnit test environment from scratch.
# Safe to re-run — skips steps that are already complete.
#
# What it does:
#   1. Ensures the Docker MySQL container is running
#   2. Creates the test database inside the container
#   3. Runs install-wp-tests.sh (downloads WordPress + WooCommerce + test scaffold)
#   4. Stubs any missing entries in WooCommerce's jetpack autoload filemap
#      (newer jetpack packages remove files like actions.php that older filemaps
#      still reference; the stubs are empty <?php files that satisfy the loader)
#
# After running this once, a Mac restart only requires:
#   docker start wp-tests-db

set -e

PASS="\033[0;32m✔\033[0m"
FAIL="\033[0;31m✘\033[0m"
INFO="\033[0;34m→\033[0m"

TMPDIR="${TMPDIR:-/tmp}"
TMPDIR="${TMPDIR%/}"   # strip trailing slash

WP_DIR="$TMPDIR/wordpress"
WC_PLUGIN_DIR="$WP_DIR/wp-content/plugins/woocommerce"
WP_TESTS_DIR="$TMPDIR/wordpress-tests-lib"

DB_NAME="wordpress_test"
DB_USER="root"
DB_PASS="root"
DB_HOST="127.0.0.1"
CONTAINER="wp-tests-db"

# ── 1. Docker MySQL container ──────────────────────────────────────────────────
echo -e "$INFO Checking Docker MySQL container ($CONTAINER)..."
if docker inspect "$CONTAINER" &>/dev/null; then
	if [ "$(docker inspect -f '{{.State.Running}}' $CONTAINER)" != "true" ]; then
		docker start "$CONTAINER"
		echo -e "   $PASS Started existing container."
	else
		echo -e "   $PASS Already running."
	fi
else
	echo "   Creating new MySQL 8 container..."
	docker run -d --name "$CONTAINER" \
		-e MYSQL_ROOT_PASSWORD="$DB_PASS" \
		-e MYSQL_DATABASE="$DB_NAME" \
		-p 3306:3306 \
		mysql:8

	echo -n "   Waiting for MySQL to be ready"
	until docker exec "$CONTAINER" mysqladmin ping -u"$DB_USER" -p"$DB_PASS" --silent 2>/dev/null; do
		sleep 2; echo -n "."
	done
	echo -e " $PASS Ready."
fi

# ── 2. WordPress + WooCommerce + test scaffold ─────────────────────────────────
echo -e "$INFO Checking WordPress test environment..."
if [ -f "$WC_PLUGIN_DIR/woocommerce.php" ] && [ -f "$WP_TESTS_DIR/includes/functions.php" ]; then
	echo -e "   $PASS Already installed."
else
	echo "   Creating test database..."
	docker exec "$CONTAINER" mysql -u"$DB_USER" -p"$DB_PASS" \
		-e "DROP DATABASE IF EXISTS $DB_NAME; CREATE DATABASE $DB_NAME;" 2>/dev/null

	echo "   Running install-wp-tests.sh (downloads WP + WC + test scaffold)..."
	echo "   This takes a few minutes on first run..."
	bash "$(dirname "$0")/install-wp-tests.sh" \
		"$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST" latest true 2>&1 | grep -v "^+" || true
	echo -e "   $PASS Done."
fi

# ── 3. Stub missing jetpack autoload filemap entries ──────────────────────────
echo -e "$INFO Checking WooCommerce jetpack autoload filemap..."
WC_VENDOR="$WC_PLUGIN_DIR/vendor"
if [ -f "$WC_VENDOR/composer/jetpack_autoload_filemap.php" ]; then
	STUBBED=$(php -r "
\$map = include '$WC_VENDOR/composer/jetpack_autoload_filemap.php';
\$n = 0;
foreach (\$map as \$entry) {
    \$path = \$entry['path'];
    if (!file_exists(\$path)) {
        \$dir = dirname(\$path);
        if (!is_dir(\$dir)) mkdir(\$dir, 0755, true);
        file_put_contents(\$path, '<?php');
        \$n++;
    }
}
echo \$n;
" 2>/dev/null)
	if [ "$STUBBED" -gt 0 ]; then
		echo -e "   $PASS Stubbed $STUBBED missing filemap entries."
	else
		echo -e "   $PASS All filemap entries present."
	fi
else
	echo -e "   $PASS No jetpack filemap found (skipping)."
fi

echo ""
echo -e "$PASS Test environment ready. Run: vendor/bin/phpunit"
echo ""
