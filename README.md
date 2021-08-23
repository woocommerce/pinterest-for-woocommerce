# Pinterest for WooCommerce

A native integration which allows you to market your store on Pinterest, including:

-   [Sync your WooCommerce products to Pinterest.](https://help.pinterest.com/en/business/article/before-you-get-started-with-catalogs)
-   Add [Save button](https://help.pinterest.com/en/business/article/save-button) to your products' images, so your visitors can save them straight to a Pinterest board.
-   Make your products and posts show up as [Rich Pins](https://help.pinterest.com/en/business/article/rich-pins) on Pinterest.
-   Track conversions with [Pinterest tag](https://help.pinterest.com/en/business/article/track-conversions-with-pinterest-tag).

## Status - _in development_

Pinterest for WooCommerce is under development. To find out more about availability and release, refer to WooCommerce.com.

## Support

This repository is not suitable for support. Please don't use our issue tracker for support requests.

### Requirements

Pinterest for WooCommerce requires recent versions of PHP (7.4 or newer), and WordPress and WooCommerce (we recommend the latest, and support the last two versions, a.k.a. L-2).

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

After cloning the repo, install dependencies:

-   `npm install` to install JavaScript dependencies.
-   `composer install` to gather PHP dependencies.

Now you can build the plugin using one of these commands:

-   `npm start`: Build a development version and watch files for changes.
-   `npm build`: Build a production version.
-   `npm build:zip`: Build and production version and package as a zip file.

### Branches

-   `develop` branch is the most up to date code.

### Development tools

There are a number of development tools available as npm scripts. Check the [`package.json`](https://github.com/woocommerce/pinterest-for-woocommerce/blob/develop/package.json) file for more.

-   `npm run lint:js`: Run [`eslint`](https://eslint.org/) to validate JavaScript code style.
-   `npm run lint:css`: Run [`stylelint`](https://stylelint.io/) to validate CSS code style.
-   `npm run lint:php`: Run [`phpcs`](https://github.com/squizlabs/PHP_CodeSniffer) to validate PHP code style.

Please use these tools to ensure your code changes are consistent with the rest of the code base. This code follows WooCommerce and WordPress standards.

This repository includes an [`EditorConfig`](https://editorconfig.org/) to automate basic code formatting. Please install the appropriate plugin for your editor.

<p align="center">
	<br/><br/>
	Made with 💜 by <a href="https://woocommerce.com/">WooCommerce</a>.<br/>
	<a href="https://woocommerce.com/careers/">We're hiring</a>! Come work with us!
</p>
