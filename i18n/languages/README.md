# Translation Files

## Why the POT file is version controlled

The `pinterest-for-woocommerce.pot` file is intentionally tracked in version control for the following reasons:

- **Internal translation workflow**: Pinterest uses this POT file internally to check for changes and send to automatic translation services
- **WooCommerce limitation**: Since WooCommerce doesn't have automatic translations, Pinterest pulls the POT files and handles translations internally
- **Manual submission process**: Translated files are manually submitted to the WordPress website periodically
- **Latest labels requirement**: Having the latest translatable strings in version control ensures the internal translation job has access to them

The POT file should be updated whenever translatable strings change in the codebase. Older versions don't need to be maintained - only the latest version is required for the internal translation pipeline.
