# Browser journeys

These Playwright tests cover Pinterest connection/settings screens and real WooCommerce orders. They run guest and customer sessions through classic and block checkout, including consent, discounts, tax and shipping. A test-only MU plugin supplies local Pinterest HTTP responses, an OAuth proxy and the WP Consent API boundary. No Pinterest account, external payment gateway or email service is needed.

## Structure

- `specs/` contains merchant and checkout journeys and their assertions.
- `fixtures/index.js` extends Playwright's `test` with store setup/cleanup, authenticated admin pages, browser request isolation and the checkout helper. Each test gets a fresh browser context; the shared WordPress store requires one worker.
- `pages/checkout.js` uses `@woocommerce/e2e-utils-playwright` for block-checkout billing and handles the suite's shortcode cart and classic checkout.
- `utils/store.js` exposes named data operations through WP-CLI. Specs pass data, never PHP expressions.
- `php/StoreFixture.php` owns test data and saved-state queries. `php/HttpFixture.php` supplies server-side HTTP responses and named WordPress hook callbacks. `fixture.php` only loads those hooks and the consent API stub.

Fixtures create data before each test and remove it during teardown, including after a failed assertion. They restore the store options and page content changed during setup. `test:browser:reset` repeats cleanup without creating new data, for example after an interrupted run. Use only a dedicated disposable store: the seed configures WooCommerce, and the MU plugin intercepts all outbound PHP HTTP and mail.

## Setup and run

With Node 24, PHP/Composer, Docker and the locked dependencies installed:

```bash
npm run test:browser:setup
npm run test:browser
npm run test:browser -- --grep 'merchant connection'
npm run test:browser -- --grep 'guest classic'
npm run test:browser:reset
```

Setup builds the plugin, starts wp-env on ports 9010/9011 and installs the MU bootstrap in the tests environment. It refuses to overwrite an existing `.wp-env.override.json`. If setup fails, remove only the override it created and rerun after resolving the error. The wp-env recipe uses current WordPress and WooCommerce on PHP 8.4. To test other versions, set `E2E_WP_VERSION`, `E2E_WC_VERSION` (each `latest`, `nightly` or an exact version) and `E2E_PHP_VERSION` before setup; it fails if the store runs different versions.

For an existing **dedicated test store** with only WooCommerce and Pinterest active:

1. Set `PINTEREST_E2E` and `DISABLE_WP_CRON` to `true` in `wp-config.php`.
2. Map this checkout to `wp-content/plugins/pinterest-for-woocommerce` and build its assets with `npm run dev`.
3. Copy `tests/browser/fixture.php` to `wp-content/mu-plugins/pinterest-e2e.php`. It loads the PHP fixtures from the mapped plugin directory; copy it again if the bootstrap changes.
4. For native WordPress, set `PINTEREST_E2E_WP_PATH=/absolute/path/to/wordpress` and `PINTEREST_E2E_URL=http://localhost:9010` to select the WP-CLI store and its browser URL.
5. Run `npm run test:browser`. Merchant credentials default to wp-env's `admin/password`; override them with `PINTEREST_E2E_ADMIN` and `PINTEREST_E2E_PASSWORD` if needed.

The MU fixture supplies the WP Consent API function; do not activate the WP Consent API plugin in this store. Use a separate WordPress directory and database from the PHP suite. Never enable the test flag or install the MU fixture on a live store.

After a wp-env run, use `npm run wp-env -- destroy`, then remove only the `.wp-env.override.json` created by setup. Browser screenshots and traces for failed tests are written to the ignored `test-results/browser` directory.

## Assertions

The merchant case rejects the first token response, displays the plugin's retry message, reconnects, saves a setting, verifies it after reload, then disconnects. It checks cleared connection state alongside retained merchant settings and the remote feed.

Eight checkout cases compare local CAPI requests and the browser tag queue with the saved order: two units at EUR24 each minus a EUR9 coupon, plus 10% tax. The physical-product guest classic checkout adds EUR7 shipping. Both trackers must report EUR39, excluding tax and shipping and retaining the discount. Consent is allowed or denied through a local cookie. Pinterest's remote tag script is blocked; only the plugin's queued calls are inspected. Undeclared Pinterest API requests fail the test; other outbound PHP HTTP and browser requests outside the local store are blocked.
