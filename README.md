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

Pinterest for WooCommerce requires recent versions of PHP (7.3 or newer), and WordPress and WooCommerce (we recommend the latest, and support the last two versions, a.k.a. L-2).

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

### Development tools

There are a number of development tools available as npm scripts. Check the [`package.json`](https://github.com/woocommerce/pinterest-for-woocommerce/blob/develop/package.json) file for more.

-   `npm run lint:js`: Run [`eslint`](https://eslint.org/) to validate JavaScript code style.
-   `npm run lint:css`: Run [`stylelint`](https://stylelint.io/) to validate CSS code style.
-   `npm run lint:php`: Run [`phpcs`](https://github.com/squizlabs/PHP_CodeSniffer) to validate PHP code style.

Please use these tools to ensure your code changes are consistent with the rest of the code base. This code follows WooCommerce and WordPress standards.

This repository includes an [`EditorConfig`](https://editorconfig.org/) to automate basic code formatting. Please install the appropriate plugin for your editor.


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

### Running Tests

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

