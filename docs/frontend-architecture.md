# Frontend architecture review

Review for [#167](https://github.com/woocommerce/pinterest-for-woocommerce/issues/167), based on `develop` at `e04faa6d42154bdf753afa97c1a947da4e4a5589` (25 September 2026). This records follow-up recommendations; it does not change runtime behavior.

## Current structure

- [Setup guide registration](../assets/source/setup-guide/index.js) registers the landing, onboarding, connection, settings, and catalog pages with WooCommerce Admin. The catalog app is imported into the same entry point.
- Settings, catalog reports, and user interactions each have a `@wordpress/data` store, with actions, selectors, resolvers, controls, and reducers. View components consume these stores through hooks.
- [Webpack configuration](../webpack.config.js) uses WooCommerce dependency extraction. It explicitly bundles `@wordpress/components` and `@wordpress/compose`, while other supported imports use platform script handles. Installed npm versions alone do not describe what the browser will run.

## Earlier feedback checked against this revision

- [#12](https://github.com/woocommerce/pinterest-for-woocommerce/issues/12) and [#194](https://github.com/woocommerce/pinterest-for-woocommerce/issues/194): the `withSelect`/`withDispatch` components have been converted to hooks. No class components remain in `assets/source`. The direct compose dependency and bundling override remain.
- [#41](https://github.com/woocommerce/pinterest-for-woocommerce/issues/41): two hooks named `useCreateNotice` remain. The [setup hook](../assets/source/setup-guide/app/helpers/effects.js) returns a callback accepting type, message, and options; the [catalog hook](../assets/source/catalog-sync/helpers/effects.js) displays an error in an effect and returns nothing. They are not interchangeable.
- [#10](https://github.com/woocommerce/pinterest-for-woocommerce/issues/10) and [#11](https://github.com/woocommerce/pinterest-for-woocommerce/issues/11): `useBodyClasses` still changes the shared admin document. Its wizard cleanup adds `wp-toolbar` even if that class was absent before mounting, and removes classes regardless of prior ownership. These concerns, and the hooks recommendation, originate in the [setup guide review](https://github.com/woocommerce/pinterest-for-woocommerce/pull/7#pullrequestreview-646482552).
- [#104](https://github.com/woocommerce/pinterest-for-woocommerce/issues/104): the [settings reducer](../assets/source/setup-guide/app/data/settings/reducer.js) and [reports reducer](../assets/source/catalog-sync/data/reports/reducer.js) assign new objects to their local `state` parameter. This is not mutation of the input state; returning those objects directly would be a style change, as the issue's discussion acknowledges.
- [#198](https://github.com/woocommerce/pinterest-for-woocommerce/issues/198): dependency extraction remains a compatibility boundary. Its WordPress 5.6 package comparisons are historical; the [plugin header](../pinterest-for-woocommerce.php) now requires WordPress 6.9 and WooCommerce 10.9.

## Recommendations

1. Keep functional components and `useSelect`/`useDispatch` as the default. Review removal of the direct compose dependency and bundling override separately from component work; transitive package users still need a compatible implementation.
2. Give hooks names and contracts that distinguish callbacks from effects. For the notice hooks, either use distinct names or build the error effect on a shared callback hook. Preserve error timing and notice options; avoid a generic abstraction for unrelated hooks.
3. Scope visual state to the component root where possible. Where WooCommerce's admin shell requires document classes, explain the integration and restore only changes owned by that effect. Test mount, unmount, pre-existing classes, and navigation between screens. Treat the [classic navigation menu](../assets/source/components/navigation-classic/main-tab-nav.js) as another host integration to check, rather than banning all document access.
4. Keep reducers immutable and store concerns separate from rendering. Prefer direct returns when editing a reducer, but do not rewrite working reducers solely to change that style. Test state transitions and preservation of unrelated state when changing their behavior.
5. Evaluate extracted dependencies against both the minimum supported platform and current releases before changing bundling. Inspect generated `*.asset.php` handles, build output, and the actual admin screens; package installation and Jest alone do not prove runtime compatibility. Revisit old bundling workarounds with that evidence.

Prioritize document-class ownership and hook contract clarity in their existing follow-up issues. Keep dependency changes separate and validate all five admin pages, including notices, onboarding controls, and navigation. The reducer suggestion needs no standalone refactor. Recheck this snapshot before applying any recommendation to a later revision.
