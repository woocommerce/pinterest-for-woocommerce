# Agent Development Guidelines for Pinterest for WooCommerce

This document provides coding agent guidelines for working with the Pinterest for WooCommerce plugin. It contains information that may be difficult for agents to discover independently and follows industry best practices for agent instruction files.

## Project Overview

Pinterest for WooCommerce is an official WordPress plugin that integrates WooCommerce stores with Pinterest. It enables:
- Product catalog sync to Pinterest
- Pinterest Save Button for products
- Rich Pins support
- Conversion tracking with Pinterest tag
- Pinterest advertising capabilities

**Important:** This is a public open-source repository maintained by WooCommerce.

## Technology Stack

### Backend
- **PHP:** 7.4+ (minimum supported version)
- **WordPress:** 5.6+ (minimum), tested up to 6.9
- **WooCommerce:** 7.0+ (minimum), tested up to 10.5
- **Architecture:** PSR-4 autoloading for modern code, WordPress conventions for legacy code
- **Dependencies:**
  - `automattic/jetpack-autoloader` - Version resolution for shared dependencies
  - `woocommerce/action-scheduler-job-framework` - Background job processing
  - `defuse/php-encryption` - Secure data encryption
  - `woocommerce/grow` - Shared utilities and compatibility checker

### Frontend
- **JavaScript:** React-based admin interface
- **Build System:** @wordpress/scripts with webpack
- **UI Framework:** @woocommerce/components, @wordpress/components
- **State Management:** @wordpress/data
- **Node:** 14.16 (pinned via `.nvmrc`)
- **npm:** 6.14.10 to <7

### Development Environment
- **Build Tools:** Gulp (legacy), webpack (modern)

## Directory Structure

```
pinterest-for-woocommerce/
├── src/                    # Modern PHP code (PSR-4: Automattic\WooCommerce\Pinterest\)
│   ├── Admin/             # Admin interface classes
│   ├── API/               # API integration classes
│   ├── Product/           # Product sync and catalog management
│   ├── Tracking/          # Analytics and conversion tracking
│   ├── Utilities/         # Helper classes and utilities
│   └── ...
├── includes/              # Legacy WordPress-style code
│   └── admin/             # Admin-specific legacy code
├── assets/                # Frontend assets
│   ├── source/            # React source files (JSX, SCSS)
│   ├── build/             # Compiled production assets (gitignored)
│   └── images/            # Static images
├── bin/                   # Development scripts
│   ├── install-wp-tests.sh  # PHPUnit test setup
│   └── lint-branch.sh      # Linting helper
├── tests/                 # Test files
│   ├── Unit/              # PHPUnit unit tests
│   ├── Integration/       # PHPUnit integration tests
│   ├── E2e/               # End-to-end tests
│   └── Helpers/           # Test utilities
├── vendor/                # PHP dependencies (gitignored, composer-managed)
├── node_modules/          # JavaScript dependencies (gitignored, npm-managed)
├── i18n/                  # Internationalization files
├── composer.json          # PHP dependency configuration
├── package.json           # JavaScript dependency configuration
├── phpcs.xml              # PHP_CodeSniffer configuration
├── pinterest-for-woocommerce.php  # Main plugin file
└── class-pinterest-for-woocommerce.php  # Main plugin class
```

## Development Workflow

### Initial Setup

```bash
# Use correct Node version
nvm use

# Install dependencies
npm install
composer install
```

### Build Commands

```bash
# Development build with file watching
npm start

# Production build
npm run build

# Build and create distribution zip
npm run build:zip
```

### Code Quality Commands

#### PHP Linting
```bash
# Run phpcs on all PHP files
composer phpcs
# OR
npm run lint:php

# Fix automatically fixable issues
composer phpcbf
# OR
npm run lint:php:fix

# Lint only changed files
composer lint

# Lint staged changes
composer lint-staged

# Lint current branch vs develop
composer lint-branch
```

#### JavaScript Linting
```bash
# Lint JavaScript files
npm run lint:js

# Fix JavaScript issues automatically
npm run lint:js:fix
```

#### CSS Linting
```bash
# Lint CSS/SCSS files
npm run lint:css

# Fix CSS issues automatically
npm run lint:css:fix
```

### Testing

#### PHPUnit Tests

```bash
# Install WordPress test environment
./bin/install-wp-tests.sh wordpress_test root root localhost

# Run tests
vendor/bin/phpunit
# OR
composer test-unit
```

#### JavaScript Tests
```bash
# Run JavaScript unit tests
npm run test:js

# Run tests in watch mode
npm run test:js:watch
```

### Internationalization

```bash
# Generate .pot file for translations
npm run i18n
```

## Coding Standards

### PHP Standards

This project follows **WooCommerce-Core** coding standards, which extend WordPress coding standards.

**Key Rules:**
- PSR-4 naming in `src/` directory (e.g., `class ProductSync` in file `ProductSync.php`)
- WordPress naming conventions in `includes/` directory (e.g., `class-pinterest-for-woocommerce-admin.php`)
- Text domain MUST be `pinterest-for-woocommerce`
- Minimum WordPress version: 5.6
- Minimum PHP version: 7.4
- PHPCompatibility checks enabled
- File comments required (except in `src/` and `tests/`)
- Function comments required with proper @param and @return tags

**PHPCS Configuration:** See `phpcs.xml` for complete ruleset.

**CRITICAL:** ALWAYS run `vendor/bin/phpcs` before committing code. CI will fail if code doesn't pass phpcs checks.

### JavaScript Standards

- Follows `@woocommerce/eslint-plugin` recommended configuration
- Uses WordPress code style conventions
- React hooks and modern JavaScript (ES6+) patterns
- Proper PropTypes or TypeScript types for components

### CSS/SCSS Standards

- Follows `@wordpress/stylelint-config/scss` configuration
- BEM-like naming conventions preferred
- Mobile-first responsive design

## Code Style

**MUST** follow WordPress coding standards in all code.

### Spacing Rules

- **Spaces inside parentheses:** `if ( condition )`, `function_name( $arg )`
- **Spaces inside brackets:** `array( 'key' => 'value' )`, `[ 1, 2, 3 ]` (PHP 5.4+ syntax)
- **Spaces inside braces:** `{ 'key': 'value' }` (JavaScript)
- **Function calls:** `__( 'text', 'pinterest-for-woocommerce' )`
- **Control structures:** `if ( condition ) {` (space before opening brace)
- **Indentation:** Tabs (4-space width), not spaces (per `.editorconfig`)
- **Line length:** 120 columns max (WordPress standard)

### Example

```php
<?php
/**
 * Example function demonstrating WordPress code style
 *
 * @param string $merchant_id The merchant identifier.
 * @param array  $args        Configuration arguments.
 * @return array
 *
 * @throws Exception When merchant creation fails.
 */
public static function update_or_create_merchant( $merchant_id, $args ) {

	$config = array(
		'merchant_domains' => get_home_url(),
		'feed_location'    => $args['feed_url'],
		'feed_format'      => 'XML',
	);

	if ( empty( $merchant_id ) ) {
		throw new Exception(
			__( 'Merchant ID is required.', 'pinterest-for-woocommerce' ),
			400
		);
	}

	try {
		$response = Base::update_or_create_merchant( $config );
	} catch ( Throwable $th ) {
		throw new Exception( $th->getMessage(), $th->getCode() );
	}

	return $response;
}
```

**Key style elements shown:**
- Spaces inside parentheses and array brackets
- Tab indentation (shown as spaces in this example, but use actual tabs)
- Array alignment for readability
- Proper docblock with `@param`, `@return`, and `@throws`
- Text domain in translation function
- Exception handling pattern

## Git Workflow

### Branching

**ALWAYS create feature branches from `develop`:**
```bash
# Create new feature branch
git checkout develop
git pull origin develop
git checkout -b feature/your-feature-name

# Create bugfix branch
git checkout -b fix/bug-description
```

**NEVER** branch from or merge directly to `main` or `master`. The main development branch is `develop`.

#### Branch Naming Format

**Format:** `<prefix>/<short-description>`

**Allowed prefixes:**
- `feature/` - New functionality or enhancements
- `fix/` - Bug fixes
- `update/` - Dependency updates or version bumps
- `refactor/` - Code refactoring without changing functionality
- `chore/` - Maintenance tasks, build config changes

**Naming conventions:**
- Use imperative mood (e.g., `add`, `fix`, `update`, not `adding`, `fixed`, `updated`)
- Use lowercase
- Use hyphens (not underscores or spaces)
- Keep descriptions short and descriptive
- Do NOT include issue/ticket numbers in branch names

**Good examples:**
```
feature/add-catalog-retry-logic
fix/product-attribute-mapping
update/bump-min-woocommerce-version
refactor/simplify-merchant-creation
chore/update-phpcs-config
```

**Bad examples:**
```
Feature/AddRetryLogic          # Wrong: capitalized
fix_bug                        # Wrong: too vague, uses underscore
feature/PINT-123-add-feature   # Wrong: includes ticket number
updateDependencies             # Wrong: missing prefix, camelCase
```

### Commit Practices

Follow these commit guidelines:
- **Concise, one-line commit messages** preferred
- **Incremental commits** - break changes into logical, self-contained commits
- **Present tense, imperative mood** (e.g., "Add feature" not "Added feature")
- **Run phpcs before committing** - CRITICAL
- **Do NOT use `--no-verify`** to skip git hooks unless absolutely necessary
- **Do NOT commit without testing** - at minimum, ensure code passes linting

**Good commit message examples:**
```
Add Pinterest catalog sync retry logic
Fix product attribute mapping for variations
Update API error handling for rate limits
Refactor admin settings validation
```

### Pull Requests

When creating PRs:
- Target the `develop` branch
- Provide clear description of changes
- Include test instructions
- Reference related issues
- Ensure all CI checks pass (phpcs, PHPUnit, JS tests)

## Common Pitfalls

### CRITICAL - NEVER Do These Things

| Pitfall | Why |
|---------|-----|
| Edit WordPress core files | Only modify plugin code |
| Edit WooCommerce plugin files | Only modify this plugin's code |
| Commit without running `vendor/bin/phpcs` first | CI will fail |
| Modify `changelog.txt` | Unless explicitly requested |
| Commit `node_modules/` or `vendor/` directories | These are gitignored |
| Commit `.env` files or credentials | Security risk |
| Use `--no-verify` or `--no-gpg-sign` | Unless explicitly required |
| Skip running tests before pushing to remote | May break production |
| Use Node versions outside 12.20.1 to <15 | Check with `nvm use` |

### CRITICAL - ALWAYS Do These Things

| Best Practice | Why |
|--------------|-----|
| Follow WooCommerce coding standards | Enforced by phpcs |
| Use text domain `pinterest-for-woocommerce` | Required for translations |
| Branch from `develop`, not main/master | Main development branch |
| Run linting before commits | `vendor/bin/phpcs`, `npm run lint:js`, `npm run lint:css` |
| Write PHPUnit tests for new PHP functionality | Ensure code quality and prevent regressions |
| Use PSR-4 naming in `src/` directory | Modern PHP autoloading standard |
| Use WordPress naming conventions in `includes/` | Legacy code compatibility |
| Check minimum version requirements | When using WordPress/WooCommerce APIs |
| Run `composer install` after pulling | Autoloader must be regenerated |
| Run `npm install` after pulling if package.json changed | Ensure dependency consistency |

### Common Mistakes

**1. Wrong namespace in `src/` files:**
```php
// ❌ WRONG
namespace Pinterest\Product;

// ✅ CORRECT
namespace Automattic\WooCommerce\Pinterest\Product;
```

**2. Wrong text domain:**
```php
// ❌ WRONG
__( 'Hello', 'pinterest' )

// ✅ CORRECT
__( 'Hello', 'pinterest-for-woocommerce' )
```

**3. Wrong file naming in `src/`:**
```php
// ❌ WRONG - WordPress style in PSR-4 directory
// File: class-product-sync.php
class Product_Sync {}

// ✅ CORRECT - PSR-4 naming
// File: ProductSync.php
class ProductSync {}
```

**4. Editing compiled files:**
```bash
# ❌ WRONG - editing compiled output
vim assets/build/index.js

# ✅ CORRECT - edit source files
vim assets/source/index.js
npm start  # recompile
```

## Architectural Decisions

### Why PSR-4 in `src/` but WordPress style in `includes/`?

**Reason:** The plugin is transitioning from WordPress conventions to modern PHP standards. New code goes in `src/` with PSR-4 autoloading for better maintainability and IDE support. Legacy code remains in `includes/` for backward compatibility.

### Why Jetpack Autoloader?

**Reason:** When multiple plugins bundle the same dependencies (e.g., Guzzle), version conflicts can occur. Jetpack Autoloader resolves these conflicts by loading the latest compatible version across all installed plugins.

### Why Action Scheduler Job Framework?

**Reason:** WordPress cron is unreliable for critical tasks (depends on site traffic). Action Scheduler provides robust background job processing with retries, error handling, failure logging, and admin UI for monitoring scheduled actions.

### Why separate WordPress/WooCommerce minimum versions?

**Reason:** WordPress and WooCommerce release independently. We support recent versions of each to balance feature availability with user adoption:

| Platform | Support Policy | Current Minimum |
|----------|---------------|-----------------|
| WordPress | Last 2 major versions | 5.6+ |
| WooCommerce | Last several major versions | 7.0+ |

Always check the plugin header in `pinterest-for-woocommerce.php` for current requirements.

### Why is Node pinned to v14.16?

**Reason:** The build toolchain and dependencies have proven difficult to update without breaking changes. Updating Node and related packages (webpack, @wordpress/scripts, etc.) requires significant testing and potential code changes. The current pinned version (v14.16, specified in `.nvmrc`) works reliably, so we maintain it until a coordinated update effort can be planned. Always use `nvm use` to ensure you're on the correct version.

## Testing Strategy

### What to Test

**Unit Tests (PHPUnit):**
- Business logic in `src/` classes
- API integration methods
- Data transformation and validation
- Utility functions

**Integration Tests (PHPUnit):**
- WordPress/WooCommerce API interactions
- Database operations
- Plugin activation/deactivation
- Settings and option handling

**JavaScript Tests (Jest):**
- React component rendering
- State management logic
- API data transformations
- Utility functions

### Test Coverage Expectations

Aim for high coverage of business logic. Test files should mirror the structure of source files:
```
src/Product/ProductSync.php → tests/Unit/Product/ProductSyncTest.php
```

## API Integration Notes

### Pinterest API

The plugin integrates with Pinterest Marketing API v5. Key concepts:
- **Merchant ID:** Unique identifier for the merchant's Pinterest account
- **Catalog ID:** ID of the product catalog synced to Pinterest
- **Feed ID:** ID of the product feed within a catalog
- **Access Token:** OAuth2 token for API authentication (stored encrypted)

### WordPress/WooCommerce Hooks

The plugin uses standard WordPress/WooCommerce hooks:
- `woocommerce_init` - Initialize plugin features
- `woocommerce_product_object_updated_props` - Detect product changes
- `woocommerce_update_product` - Trigger product sync
- Custom action hooks for extensibility (prefixed with `pinterest_for_woocommerce_`)

## Performance Considerations

- **Product sync runs in background jobs** via Action Scheduler
- **Rate limiting** applies to Pinterest API calls (respect `X-RateLimit-*` headers)
- **Batch operations** for large catalogs (up to 1000 products per batch)
- **Caching** used for settings and API responses where appropriate
- **Asset minification** in production builds

## Security Guidelines

### Data Handling
- **API credentials encrypted** using defuse/php-encryption
- **Nonce verification** on all AJAX and form submissions
- **Capability checks** before admin operations
- **Input sanitization** on all user-provided data
- **Output escaping** when rendering data

### Confidentiality
This is a **public open-source repository**. NEVER commit:
- API keys, tokens, or credentials
- Customer data or PII
- Internal company information
- Sensitive security details
- `.env` files or local configuration

Use `.gitignore` properly and audit commits before pushing.

## Debugging

### Enable Debug Mode
```php
// In wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

### Useful Debug Functions
```php
// Log to debug.log
error_log( print_r( $variable, true ) );

// WooCommerce logger
wc_get_logger()->debug( 'Message', [ 'source' => 'pinterest-for-woocommerce' ] );
```

### Common Debug Scenarios

**Product not syncing:**
1. Check Action Scheduler admin page: WooCommerce → Status → Scheduled Actions
2. Search for actions with group `pinterest-for-woocommerce`
3. Check for failed actions and error logs

**API errors:**
1. Check `wp-content/debug.log` for API response errors
2. Verify credentials in plugin settings
3. Check Pinterest API status page

## Browser Support

Per WordPress Core Handbook, we support:
- Last 2 versions of Chrome, Firefox, Safari, Edge, Opera
- Last 1 version of ChromeAndroid, Android browser
- Last 2 versions of iOS Safari
- Browsers with >1% usage share

**We do NOT support Internet Explorer.**

## Resources

### Documentation
- [Pinterest Marketing API Docs](https://developers.pinterest.com/docs/api/v5/)
- [WooCommerce Developer Docs](https://woocommerce.com/documentation/plugins/woocommerce/)
- [WordPress Developer Handbook](https://developer.wordpress.org/)
- [Action Scheduler](https://actionscheduler.org/)

### Internal Files
- `README.md` - General development info and setup instructions
- `composer.json` - PHP dependencies and scripts
- `package.json` - JavaScript dependencies and scripts
- `phpcs.xml` - Complete phpcs ruleset
- `.editorconfig` - Editor formatting rules

## Getting Help

### This is a Public Repository
- **Issue tracker:** For bug reports and feature requests (not support)
- **Code of conduct:** Follow WordPress and WooCommerce community guidelines
- **Contributing:** Follow the development workflow outlined in this document

---

**Last Updated:** 2026-02-25
**Maintained by:** WooCommerce Team
**License:** GPL-3.0-or-later
