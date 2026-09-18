# Deprecating public functions and methods

Use `wc_deprecated_function()` for plugin functions and methods. It accepts `__METHOD__` for class methods and keeps warnings out of AJAX and REST response bodies. Code that can run before WooCommerce loads can use WordPress's `_deprecated_function()` instead. Do not add a custom deprecation framework.

Keep the existing public entry point, signature, return value and exceptions. When a replacement exists, leave a forwarding method and preserve late static binding where the old method used it:

```php
/**
 * @deprecated x.x.x Use new_method().
 */
public static function old_method( $value ) {
    wc_deprecated_function( __METHOD__, 'x.x.x', __CLASS__ . '::new_method' );
    return static::new_method( $value );
}
```

Use the release version that introduces the warning; `x.x.x` is the repository's placeholder until release preparation. If there is no equivalent replacement, omit the third argument and keep the old behavior. Document any manual migration steps instead of suggesting a replacement with a different return type.

Add a regression test that expects the deprecation notice and checks the old call's result. Do not remove a public method or change its visibility merely because the plugin no longer calls it.

Removal requires a separate release decision, a review of affected integrations and published migration instructions. A warning or elapsed time alone does not authorize removal. Keep the forwarding method until that decision is made.
