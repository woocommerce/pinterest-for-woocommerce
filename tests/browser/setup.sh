#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."
if [ -e .wp-env.override.json ]; then
  echo 'Use the documented existing-environment setup; refusing to overwrite .wp-env.override.json.' >&2
  exit 1
fi
cat > .wp-env.override.json <<'JSON'
{"plugins":["https://downloads.wordpress.org/plugin/woocommerce.zip"],"mappings":{"wp-content/plugins/pinterest-for-woocommerce":"."},"phpVersion":"8.4","port":9010,"testsPort":9011,"config":{"PINTEREST_E2E":true,"DISABLE_WP_CRON":true,"WP_DEBUG_DISPLAY":false}}
JSON
npm run dev
npx wp-env start
npx wp-env run tests-cli wp plugin activate woocommerce pinterest-for-woocommerce
npx wp-env run tests-cli mkdir -p /var/www/html/wp-content/mu-plugins
npx wp-env run tests-cli cp /var/www/html/wp-content/plugins/pinterest-for-woocommerce/tests/browser/fixture.php /var/www/html/wp-content/mu-plugins/pinterest-e2e.php
npm run test:browser:reset
PLAYWRIGHT_SKIP_BROWSER_GC=1 npx playwright install chromium
