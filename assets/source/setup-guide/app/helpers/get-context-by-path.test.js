jest.mock( '@woocommerce/navigation' );

/**
 * External dependencies
 */
import { getPath } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import getContextByPath from './get-context-by-path';

describe( 'Get Context By Path aux. Function', () => {
	it( 'Formats the path', () => {
		getPath.mockImplementation( () => '/pinterest/settings' );
		const context = getContextByPath();
		expect( context ).toStrictEqual( 'pinterest_settings' );
	} );

	it( 'Returns pinterest if no path is provided', () => {
		getPath.mockImplementation( () => '/' );
		const context = getContextByPath();
		expect( context ).toBe( '' );
	} );

	it( 'Returns pinterest if a bad path is provided', () => {
		getPath.mockImplementation( () => {
			//creating some kind of weird and unpredictable error here
			const a = 55;
			return a.substring( 1 );
		} );
		let context = getContextByPath();
		expect( context ).toBe( '' );

		getPath.mockImplementation( () => 55 );
		context = getContextByPath();
		expect( context ).toBe( '' );
	} );
} );
