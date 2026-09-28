#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."
if [ -e .wp-env.override.json ]; then
  echo 'Use the documented existing-environment setup; refusing to overwrite .wp-env.override.json.' >&2
  exit 1
fi

# Optional versions: `latest` (default), `nightly` or an exact version such as 6.8.1 or 10.2.0-rc.1.
WP_REQUESTED=${E2E_WP_VERSION:-latest}
WC_REQUESTED=${E2E_WC_VERSION:-latest}
PHP_REQUESTED=${E2E_PHP_VERSION:-8.4}
for value in "$WP_REQUESTED" "$WC_REQUESTED" "$PHP_REQUESTED"; do
  if [[ ! $value =~ ^[0-9A-Za-z.-]+$ ]]; then
    echo "Invalid version: $value" >&2
    exit 1
  fi
done

# WordPress: keep .wp-env.json's latest core unless another version is requested.
# WordPress.org publishes x.y.0 as x.y (wordpress-x.y.zip).
WP_EXPECTED=$WP_REQUESTED
if [[ $WP_REQUESTED =~ ^[0-9]+\.[0-9]+\.0$ ]]; then
  WP_EXPECTED=${WP_REQUESTED%.0}
fi
case $WP_REQUESTED in
  latest) WP_CORE='' ;;
  nightly | trunk) WP_CORE='"core":"https://wordpress.org/nightly-builds/wordpress-latest.zip",' ;;
  *) WP_CORE="\"core\":\"https://wordpress.org/wordpress-$WP_EXPECTED.zip\"," ;;
esac

# WooCommerce is mapped to wp-content/plugins/woocommerce, because wp-env names a zip plugin
# after its file name, which differs for nightly and pre-release builds.
case $WC_REQUESTED in
  latest) WC_ZIP='https://downloads.wordpress.org/plugin/woocommerce.zip' ;;
  nightly | trunk) WC_ZIP='https://github.com/woocommerce/woocommerce/releases/download/nightly/woocommerce-trunk-nightly.zip' ;;
  *) WC_ZIP="https://downloads.wordpress.org/plugin/woocommerce.$WC_REQUESTED.zip" ;;
esac

cat > .wp-env.override.json <<JSON
{${WP_CORE}"plugins":[],"mappings":{"wp-content/plugins/woocommerce":"$WC_ZIP","wp-content/plugins/pinterest-for-woocommerce":"."},"phpVersion":"$PHP_REQUESTED","port":9010,"testsPort":9011,"config":{"PINTEREST_E2E":true,"DISABLE_WP_CRON":true,"WP_DEBUG_DISPLAY":false}}
JSON
npm run dev
npx wp-env start
npx wp-env run tests-cli wp plugin activate woocommerce pinterest-for-woocommerce

# Fail when the store does not run exactly the requested versions.
WP_INSTALLED=$(npx wp-env run tests-cli wp core version | tr -d '\r')
WC_INSTALLED=$(npx wp-env run tests-cli wp plugin get woocommerce --field=version | tr -d '\r')
PHP_INSTALLED=$(npx wp-env run tests-cli php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' | tr -d '\r')
echo "Installed: WordPress $WP_INSTALLED, WooCommerce $WC_INSTALLED, PHP $PHP_INSTALLED"
if [[ $WP_REQUESTED != latest && $WP_REQUESTED != nightly && $WP_REQUESTED != trunk && $WP_INSTALLED != "$WP_EXPECTED" ]] ||
  [[ $WC_REQUESTED != latest && $WC_REQUESTED != nightly && $WC_REQUESTED != trunk && $WC_INSTALLED != "$WC_REQUESTED" ]] ||
  [[ $PHP_INSTALLED != "$PHP_REQUESTED" ]]; then
  echo "Requested WordPress $WP_REQUESTED, WooCommerce $WC_REQUESTED, PHP $PHP_REQUESTED." >&2
  exit 1
fi

npx wp-env run tests-cli mkdir -p /var/www/html/wp-content/mu-plugins
npx wp-env run tests-cli cp /var/www/html/wp-content/plugins/pinterest-for-woocommerce/tests/browser/fixture.php /var/www/html/wp-content/mu-plugins/pinterest-e2e.php
npm run test:browser:reset
PLAYWRIGHT_SKIP_BROWSER_GC=1 npx playwright install chromium
