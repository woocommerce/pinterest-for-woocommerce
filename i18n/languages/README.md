# Translation Files

## Why the POT file is version controlled

The `pinterest-for-woocommerce.pot` file is intentionally tracked in version control for the following reasons:

- **Internal translation workflow**: Pinterest uses this POT file internally to check for changes and send to automatic translation services
- **WooCommerce limitation**: Since WooCommerce doesn't have automatic translations, Pinterest pulls the POT files and handles translations internally
- **Manual submission process**: Translated files are manually submitted to the WordPress website periodically
- **Latest labels requirement**: Having the latest translatable strings in version control ensures the internal translation job has access to them

The translation job reads this file from the default branch, `trunk`. Keep its path and name, and check with Pinterest before moving it or changing the branch it lives on.

The release workflow (`.github/workflows/release-start.yml`) regenerates it with `npm run i18n` for every release. Run the same command and commit the result to refresh it between releases. Older versions don't need to be maintained, only the latest one.
