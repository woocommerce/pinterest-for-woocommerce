# Pinterest for WooCommerce - Testing & Flows

This file is a collection of the flows (key UI and features) that are implemented. 

These flows can be used when testing PRs or releases, and as a basis for documentation.

## Onboarding & connection

- Merchant can connect and disconnect to a valid Pinterest Business account.
	- Helpful error messages are shown if connection fails (e.g. Pinterest account is invalid or unverified).
	- Connection status is shown in WordPress admin dashboard.
	- Merchant can verify store domain (host) with Pinterest.
	- Unauthorised users cannot connect or disconnect or verify domain.

## Catalog (product) sync

_tbc_

## Pinterest pins

- Merchant can expose [OpenGraph metadata](https://ogp.me/) for products and posts to enable Rich Pins.
	- Metadata passes validation in [Pinterest Rich Pins Validator](https://developers.pinterest.com/tools/url-debugger/).
	- Merchant can disable for products and/or pages.
	- Merchant can adjust what metadata is exposed via admin settings.

## Conversion tracking

_tbc_


## Miscellaneous

- Pinterest admin screens are visible and accessible in all supported WooCommerce Admin navigation variations (legacy WP sidebar and [new unified nav](https://developer.woocommerce.com/2021/01/15/call-to-action-create-access-for-your-extension-in-the-new-woocommerce-navigation/)).
