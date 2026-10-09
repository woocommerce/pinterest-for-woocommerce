/**
 * External dependencies
 */
import { afterEach, assert, beforeEach, vi } from 'vitest';

/*
 * Fail any test that writes to the console without expecting it, as
 * `@wordpress/jest-console` did. A test expects output by replacing the method
 * first and asserting on that spy:
 *
 *     const warn = vi.spyOn( console, 'warn' ).mockImplementation( () => {} );
 *     ...
 *     expect( warn ).toHaveBeenCalledWith( 'message' );
 */
const methods = [ 'error', 'info', 'log', 'warn' ];
let spies = [];
let unexpected = [];

beforeEach( () => {
	unexpected = [];
	spies = methods.map( ( method ) =>
		vi.spyOn( console, method ).mockImplementation( ( ...args ) => {
			unexpected.push( { method, args } );
		} )
	);
} );

afterEach( () => {
	spies.forEach( ( spy, index ) => {
		// `vi.resetAllMocks()` in a test file drops the recorder above, but the
		// spy still holds the calls made since.
		if ( spy.getMockImplementation() === undefined ) {
			spy.mock.calls.forEach( ( args ) => {
				unexpected.push( { method: methods[ index ], args } );
			} );
		}
		spy.mockRestore();
	} );
	assert.deepEqual(
		unexpected,
		[],
		'Unexpected console output. Spy on the console method in the test to expect it.'
	);
} );
