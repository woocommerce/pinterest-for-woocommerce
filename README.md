# Pinterest for WooCommerce

[![PHP Unit Tests](https://github.com/woocommerce/pinterest-for-woocommerce/actions/workflows/php-unit-tests.yml/badge.svg)](https://github.com/woocommerce/pinterest-for-woocommerce/actions/workflows/php-unit-tests.yml)
[![JavaScript Unit Tests](https://github.com/woocommerce/pinterest-for-woocommerce/actions/workflows/js-unit-tests.yml/badge.svg)](https://github.com/woocommerce/pinterest-for-woocommerce/actions/workflows/js-unit-tests.yml)
[![PHP Coding Standards - PR Changed Files](https://github.com/woocommerce/pinterest-for-woocommerce/actions/workflows/php-cs-on-changes.yml/badge.svg)](https://github.com/woocommerce/pinterest-for-woocommerce/actions/workflows/php-cs-on-changes.yml)
[![JavaScript and CSS Linting](https://github.com/woocommerce/pinterest-for-woocommerce/actions/workflows/js-css-linting.yml/badge.svg)](https://github.com/woocommerce/pinterest-for-woocommerce/actions/workflows/js-css-linting.yml)

A native integration which allows you to market your store on Pinterest, including:

-   [Sync your WooCommerce products to Pinterest.](https://help.pinterest.com/en/business/article/before-you-get-started-with-catalogs)
-   Allow your visitors to [save products to their Pinterest boards](https://help.pinterest.com/en/business/article/save-button).
-   Make your products and posts show up as [Rich Pins](https://help.pinterest.com/en/business/article/rich-pins) on Pinterest.
-   Track conversions with [Pinterest tag](https://help.pinterest.com/en/business/article/track-conversions-with-pinterest-tag).

## Status - _in development_

Pinterest for WooCommerce is under development. To find out more about availability and release, refer to WooCommerce.com.

## Support

This repository is not suitable for support. Please don't use our issue tracker for support requests.

### Requirements

Pinterest for WooCommerce requires PHP 7.4 or newer, WordPress 6.9 or newer, and WooCommerce 10.9 or newer.

See [pinterest-for-woocommerce.php](https://github.com/woocommerce/pinterest-for-woocommerce/blob/develop/pinterest-for-woocommerce.php) for current required versions.

### Supported browsers

As per [WordPress Core Handbook](https://make.wordpress.org/core/handbook/best-practices/browser-support/) we currently support:

> -   Last 1 Android versions.
> -   Last 1 ChromeAndroid versions.
> -   Last 2 Chrome versions.
> -   Last 2 Firefox versions.
> -   Last 2 Safari versions.
> -   Last 2 iOS versions.
> -   Last 2 Edge versions.
> -   Last 2 Opera versions.
> -   Browsers with > 1% usage based on [can I use browser usage table](https://caniuse.com/usage-table)

:warning: We do not support Internet Explorer.

## Development

After cloning the repo. Remember to use the appropriate node version

- `nvm use` to autoselect the node version based on `.nvmrc` file.

Then, install dependencies:

-   `npm install` to install JavaScript dependencies.
-   `composer install` to gather PHP dependencies.

Now you can build the plugin using one of these commands:

-   `npm start`: Build a development version and watch files for changes.
-   `npm build`: Build a production version.
-   `npm build:zip`: Build and production version and package as a zip file.

### Branches

-   `develop` branch is the most up-to-date code.

### AI code reviews

[CodeRabbit](https://docs.coderabbit.ai/platforms/github-com) requires its GitHub App
to have access to this repository. A WooCommerce organization owner must grant that
access; committing [`.coderabbit.yaml`](.coderabbit.yaml) does not install the app.

Once enabled, CodeRabbit reviews non-draft PRs targeting the default branch when
opened or marked ready, then reviews new commits. The configuration follows the
default branch if it is renamed. Reviews use the repository's agent guidance and
pause after five reviewed commits to limit repeated reviews.

Use these [PR comment commands](https://docs.coderabbit.ai/guides/commands):

-   `@coderabbitai review`: review changes since the last review.
-   `@coderabbitai full review`: review the whole PR again.
-   `@coderabbitai pause`: pause automatic reviews.
-   `@coderabbitai resume`: resume automatic reviews.

Add `@coderabbitai ignore` to the PR description to skip automatic reviews for that
PR. Human review and CI checks still apply.

### Development tools

There are a number of development tools available as npm scripts. Check the [`package.json`](https://github.com/woocommerce/pinterest-for-woocommerce/blob/develop/package.json) file for more.

-   `npm run lint:js`: Run [`eslint`](https://eslint.org/) to validate JavaScript code style.
-   `npm run lint:css`: Run [`stylelint`](https://stylelint.io/) to validate CSS code style.
-   `npm run lint:php`: Run [`phpcs`](https://github.com/squizlabs/PHP_CodeSniffer) to validate PHP code style.

Use `composer check:php` for the same PHP standards check as CI. It compares
committed PHP changes with the merge base of `origin/develop`; fetch that branch
first, or pass another base with `composer check:php -- base-ref`. New errors
and warnings at severity 5 or higher fail. Existing findings remain excluded by
`phpcs-changed`. Commit changes before this check; `composer lint` and
`composer lint-staged` remain available for work in progress.

Use `composer phpcs -- .` for the full-tree debt report. Review existing debt
separately from the changed-line gate.

Please use these tools to ensure your code changes are consistent with the rest of the code base. This code follows WooCommerce and WordPress standards.

This repository includes an [`EditorConfig`](https://editorconfig.org/) to automate basic code formatting. Please install the appropriate plugin for your editor.


## JavaScript unit tests

Use the Node version in `.nvmrc`, then install locked dependencies and run Jest:

```bash
nvm use
npm ci
npm run test:js -- --runInBand
npm run test:js -- --runInBand --runTestsByPath assets/source/setup-guide/app/data/settings/settings.test.js
```

Both commands collect line and branch coverage in `coverage/index.html`
and `coverage/coverage-summary.json`. Coverage includes every production JavaScript
module under `assets/source`, including modules no test imports; test files, the
`assets/source/tests` fixtures/examples and generated output are excluded.
Use `--coverage=false` for a faster focused run. No live Pinterest account is needed.

Product attributes are server-rendered PHP forms; their submitted-value tests use
the existing PHP runner: `composer test-unit -- --filter AttributesFormTest`.
Prepare its WordPress/WooCommerce environment as described below.

## PHPUnit

### Prerequisites

Install [`composer`](https://getcomposer.org/), `git`, `svn`, and either `wget` or `curl`.

Change to the plugin root directory and type:

```bash
$ composer install
```


### Install Test Dependencies

To run the unit tests you need WordPress, [WooCommerce](https://github.com/woocommerce/woocommerce), and the WordPress Unit Test lib (included in the [core development repository](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)).

Install them using the `install-wp-tests.sh` script:

```bash
$ ./bin/install-wp-tests.sh <db-name> <db-user> <db-pass> <db-host>
```

Example:

```bash
$ ./bin/install-wp-tests.sh wordpress_tests root root localhost
```

To test the minimum supported versions, use a fresh test directory and a dedicated database:

```bash
$ WC_VERSION=10.9.0 ./bin/install-wp-tests.sh wordpress_tests root root localhost 6.9.0
```

`WC_VERSION` accepts a release tag; `latest` and the default `trunk` select the latest stable release. The script reuses an existing WooCommerce directory, so use a fresh `TMPDIR` and `WP_CORE_DIR` when switching versions.

This script installs the test dependencies into your system's temporary directory and also creates a test database.

You can also specify the path to their directories by setting the following environment variables:

-   `WP_TESTS_DIR`: WordPress Unit Test lib directory
-   `WP_CORE_DIR`: WordPress core directory
-   `WC_DIR`: WooCommerce directory

### PHP Tests in wp-env

`npm run test:php:wp-env` runs the PHPUnit suite inside [`@wordpress/env`](https://www.npmjs.com/package/@wordpress/env)'s `tests-cli` container. Compared to `./bin/install-wp-tests.sh`, this path needs no host MySQL or `svn`, and wp-env scopes its containers by working-directory hash so concurrent runs from separate worktrees stay isolated.

#### Prerequisites

-   Docker (Docker Desktop on macOS/Windows is enough).
-   A local development checkout of WooCommerce. The plugin's `tests/bootstrap.php` requires WooCommerce's `tests/legacy/bootstrap.php`, which ships only in the WooCommerce source repo — not in the WordPress.org zip the base `.wp-env.json` downloads.

#### One-time setup

Create `.wp-env.override.json` alongside `.wp-env.json` to swap the base WooCommerce zip for your local checkout (adjust the path to wherever you cloned WooCommerce):

```json
{
	"plugins": ["../woocommerce/plugins/woocommerce", "."]
}
```

`plugins` in the override file _replaces_ the base list, so include both WooCommerce and `.` (this plugin). If `vendor/` is missing inside your WooCommerce checkout, run `composer install` in that directory before starting wp-env.

#### Running tests

```bash
npx wp-env start                         # ~30–90s first run, faster afterwards
npm run test:php:wp-env                  # run the full PHPUnit suite
npm run test:php:wp-env -- --filter ProductSyncTest --testsuite=unit
npx wp-env stop                          # tear down when finished
```

Anything after `--` is forwarded to `phpunit`, so PHPUnit flags such as `--filter`, `--testsuite`, and `--group` work as usual.

### Saved-data regression tests

`composer test-unit` runs all PHP suites. Use `composer test-unit -- --filter SavedDataTest` for historical upgrades, product-editor attribute saves and exact order conversion values in both HPOS and legacy storage. HTTP responses are local fixtures; no Pinterest account is needed. The 1.3.9 JSON fixture records its source version and stored settings; the test separately seeds the encrypted pre-v5 token. A second case covers the pre-1.0.10 single local feed ID.

Create a separate database and install directory for each dependency combination:

```bash
pinterest_test_root="$(mktemp -d /tmp/pinterest-tests.XXXXXX)"
export WP_CORE_DIR="$pinterest_test_root/wordpress"
export WP_TESTS_DIR="$pinterest_test_root/wordpress-tests-lib"
TMPDIR="$pinterest_test_root" WC_VERSION=10.9.0 ./bin/install-wp-tests.sh pinterest_regression_test root "$PINTEREST_TEST_DB_PASSWORD" 127.0.0.1 6.9.0
composer test-unit -- --filter SavedDataTest
```

Use a dedicated empty test database: the WordPress bootstrap replaces its test prefix tables on each run. Test cases roll back their fixtures, so rerunning the command resets products, orders and options. To change supported WordPress or WooCommerce versions, choose the matching installer arguments and a new directory and database; the installer reuses existing downloads. Missing dependencies are reported by the bootstrap before PHPUnit runs. The existing wp-env runner accepts the same `--filter` and `--testsuite` arguments.

With Xdebug installed, run `XDEBUG_MODE=coverage composer test-unit -- --coverage-html coverage/php --path-coverage` for line and branch/path coverage. PCOV supports the same HTML report without `--path-coverage` and reports lines only. Coverage includes unexecuted plugin PHP source and excludes tests and vendor code. Open `coverage/php/index.html`; a missing driver cannot produce a report.

### Running Tests

The PHP suite runs on PHP 7.4, 8.3 and 8.4 in both PHP workflows. To reproduce the
newer-PHP lane locally, put PHP 8.4 on your PATH, install the locked Composer
dependencies, and prepare the isolated WordPress/WooCommerce test environment
above. Run `composer test-unit`, the same command used in CI. The bootstrap prints
the actual PHP, WordPress and WooCommerce versions, so a configured version can be
checked against the installed version. For wp-env, set `"phpVersion": "8.4"` in
your local `.wp-env.override.json` before starting the environment.

Change to the plugin root directory and type:

```bash
$ vendor/bin/phpunit
```

The tests will execute, and you'll be presented with a summary.

<p align="center">
	<br/><br/>
	Made with 💜 by <a href="https://woocommerce.com/">WooCommerce</a>.<br/>
	<a href="https://woocommerce.com/careers/">We're hiring</a>! Come work with us!
</p>

### PHPStan

After `composer install`, run `composer lint:phpstan`. CI runs the same command
on PHP, Composer and PHPStan configuration changes using PHP 8.4. PHPStan checks
owned production PHP at level 0 against the plugin's minimum PHP version.
WordPress and WooCommerce stubs supply core symbols. Vendor code, generated files,
tests and build tools are outside analysis.

The baseline records existing findings by message, rule, file and count. New
findings and unmatched baseline entries fail the check. Run
`composer lint:phpstan:baseline:update` only for a reviewed baseline change;
never regenerate it to hide new findings. PHPCS and PHPCompatibility remain separate checks.

### Browser regression journeys

See [tests/browser/README.md](tests/browser/README.md) for isolated browser setup, merchant and checkout journeys, focused runs and fixture reset.
