/**
 * React 18.3 reports deprecations from the published builds of
 * `@wordpress/components` 12 (`Card` default props) and
 * `@woocommerce/components` 5 (`Link` legacy context). They cannot be fixed
 * from this repository, and React reports each one only in whichever test
 * renders the component first.
 */
const KNOWN_DEPRECATIONS = {
	Card: 'Support for defaultProps will be removed from function components',
	Link: 'uses the legacy contextTypes API',
};

/**
 * Declares the React deprecations a suite is expected to trigger.
 * Any other `console.error` call still fails the test.
 *
 * Call it inside a `describe` block, so the check runs before
 * `@wordpress/jest-console` rejects unexpected errors.
 *
 * @param {...string} components Names of the components known to trigger a deprecation.
 */
export function expectKnownReactDeprecations( ...components ) {
	/* eslint-disable no-console -- Validate only the known warnings after each independent test. */
	afterEach( () => {
		const { calls } = console.error.mock;
		if ( ! calls.length ) {
			return;
		}

		expect( console ).toHaveErrored();
		calls.forEach( ( [ format, component ] ) => {
			expect( components ).toContain( component );
			expect( format ).toContain( KNOWN_DEPRECATIONS[ component ] );
		} );
		// React reports each deprecation once per component.
		expect( calls ).toHaveLength(
			new Set( calls.map( ( [ , component ] ) => component ) ).size
		);
	} );
	/* eslint-enable no-console */
}
