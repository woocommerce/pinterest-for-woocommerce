# Browser journeys

These Playwright tests cover the merchant connection/settings screens and real
WooCommerce orders. They use guest and customer browser sessions for classic and
block checkout. A test-only MU plugin supplies fake Pinterest HTTP responses,
a local connection proxy, and the WP Consent API boundary. No Pinterest account,
payment gateway or email service is needed.

With Node 24, PHP/Composer, Docker and the locked dependencies installed:

```bash
npm run test:browser:setup
npm run test:browser
npm run test:browser -- --grep 'merchant connection'
npm run test:browser -- --grep 'guest classic'
npm run test:browser:reset
```

Setup builds the plugin, starts wp-env on private ports 9010/9011 and installs the
fixture in the tests environment. It refuses to overwrite an existing
`.wp-env.override.json`. If setup fails, remove only the override it created and rerun
the setup command after resolving the error. The wp-env recipe uses current WordPress
and WooCommerce; local validation used WordPress 6.9 and WooCommerce 10.9. For an existing **dedicated test store**, set
`PINTEREST_E2E` and `DISABLE_WP_CRON` to true in wp-config, activate WooCommerce and
Pinterest, and copy `fixture.php` to `wp-content/mu-plugins/pinterest-e2e.php`.
Then run the reset command.
Whenever `fixture.php` changes, copy it into the test store again before running.
 For native local WordPress, set
`PINTEREST_E2E_WP_PATH=/absolute/path/to/wordpress` and
`PINTEREST_E2E_URL=http://localhost:9010`; these select the same WP-CLI seed and
browser tests. The native plugin path must be `wp-content/plugins/pinterest-for-woocommerce`.
Merchant credentials default to wp-env's admin/password and can
be set with `PINTEREST_E2E_ADMIN` and `PINTEREST_E2E_PASSWORD`.

Use a separate WordPress directory and database from the PHP suite. Each test
resets the suite's own products, coupons, checkout pages and tagged orders, and
reinitializes its local Pinterest settings. It requires the explicit test flag;
never enable that flag or install the MU fixture on a live store. Test customers
have the customer role. Browser artifacts are written to ignored `test-results`.

The merchant case rejects the first token response, displays the plugin's retry
message, reconnects, saves a setting and verifies it after reload, then disconnects
and checks cleared connection state and retained merchant settings/remote feed.
Checkout cases compare local CAPI requests and the plugin's browser tag queue
with the saved order: two products at EUR24 minus a EUR9 coupon, plus 10% tax.
The physical-product guest checkout adds EUR7 shipping. Both trackers must report
EUR39, excluding tax and shipping and retaining the discount. Consent is
explicitly allowed/denied through a local cookie. Pinterest's remote tag script
is blocked; only the plugin's queued calls are inspected. All outbound PHP HTTP
is intercepted and browser requests outside the local store are blocked.
