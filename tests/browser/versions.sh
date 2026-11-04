#!/usr/bin/env bash
# Pins the browser test store (.wp-env.test.json) to other WordPress, WooCommerce or PHP versions.
#
#   bash tests/browser/versions.sh         Write .wp-env.test.override.json; run before npm run env:test:e2e:setup.
#   bash tests/browser/versions.sh check   Fail when the running tests site has other versions.
#
# Versions: E2E_WP_VERSION and E2E_WC_VERSION take `latest` (default), `nightly` or an exact version
# such as 6.8.1 or 10.2.0-rc.1; E2E_PHP_VERSION defaults to 8.4.
set -euo pipefail
cd "$(dirname "$0")/../.."

WP_REQUESTED=${E2E_WP_VERSION:-latest}
WC_REQUESTED=${E2E_WC_VERSION:-latest}
PHP_REQUESTED=${E2E_PHP_VERSION:-8.4}
for value in "$WP_REQUESTED" "$WC_REQUESTED" "$PHP_REQUESTED"; do
  if [[ ! $value =~ ^[0-9A-Za-z.-]+$ ]]; then
    echo "Invalid version: $value" >&2
    exit 1
  fi
done

# WordPress.org publishes x.y.0 as x.y (wordpress-x.y.zip).
WP_EXPECTED=$WP_REQUESTED
if [[ $WP_REQUESTED =~ ^[0-9]+\.[0-9]+\.0$ ]]; then
  WP_EXPECTED=${WP_REQUESTED%.0}
fi

if [ "${1:-}" = check ]; then
  WP_INSTALLED=$(npm run --silent wp-env:test -- run cli wp core version | tr -d '\r')
  WC_INSTALLED=$(npm run --silent wp-env:test -- run cli wp plugin get woocommerce --field=version | tr -d '\r')
  PHP_INSTALLED=$(npm run --silent wp-env:test -- run cli php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' | tr -d '\r')
  echo "Installed: WordPress $WP_INSTALLED, WooCommerce $WC_INSTALLED, PHP $PHP_INSTALLED"
  if [[ $WP_REQUESTED != latest && $WP_REQUESTED != nightly && $WP_REQUESTED != trunk && $WP_INSTALLED != "$WP_EXPECTED" ]] ||
    [[ $WC_REQUESTED != latest && $WC_REQUESTED != nightly && $WC_REQUESTED != trunk && $WC_INSTALLED != "$WC_REQUESTED" ]] ||
    [[ $PHP_INSTALLED != "$PHP_REQUESTED" ]]; then
    echo "Requested WordPress $WP_REQUESTED, WooCommerce $WC_REQUESTED, PHP $PHP_REQUESTED." >&2
    exit 1
  fi
  exit 0
fi

case $WP_REQUESTED in
  latest) WP_CORE='' ;;
  nightly | trunk) WP_CORE='"core":"https://wordpress.org/nightly-builds/wordpress-latest.zip",' ;;
  *) WP_CORE="\"core\":\"https://wordpress.org/wordpress-$WP_EXPECTED.zip\"," ;;
esac

# .wp-env.test.json maps WooCommerce to wp-content/plugins/woocommerce, because wp-env names a zip
# plugin after its file name, which differs for nightly and pre-release builds.
case $WC_REQUESTED in
  latest) WC_ZIP='https://downloads.wordpress.org/plugin/woocommerce.zip' ;;
  nightly | trunk) WC_ZIP='https://github.com/woocommerce/woocommerce/releases/download/nightly/woocommerce-trunk-nightly.zip' ;;
  *) WC_ZIP="https://downloads.wordpress.org/plugin/woocommerce.$WC_REQUESTED.zip" ;;
esac

cat > .wp-env.test.override.json <<JSON
{${WP_CORE}"mappings":{"./wp-content/plugins/woocommerce":"$WC_ZIP"},"phpVersion":"$PHP_REQUESTED"}
JSON
echo "Wrote .wp-env.test.override.json: WordPress $WP_REQUESTED, WooCommerce $WC_REQUESTED, PHP $PHP_REQUESTED."
