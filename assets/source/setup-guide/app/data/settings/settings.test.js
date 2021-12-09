/**
 * Internal dependencies
 */
import settingsReducer from './reducer';
import { setUpdatedData } from './actions';

describe( 'Data Store Settings', () => {
	it( 'SET_UPDATE_DATA', () => {
		let state = { foo: 'bar', updatedData: {} };

		// basic set
		let data = { baz: 'bar' };
		state = settingsReducer( state, setUpdatedData( data ) );

		expect( state ).toStrictEqual( {
			foo: 'bar',
			updatedData: data,
		} );

		// basic update
		data = { baz: 'foo' };
		state = settingsReducer( state, setUpdatedData( data ) );

		expect( state ).toStrictEqual( {
			foo: 'bar',
			updatedData: data,
		} );

		// multiple update
		data = { baz: 'bar', foo: 'bar' };
		state = settingsReducer( state, setUpdatedData( data ) );

		expect( state ).toStrictEqual( {
			foo: 'bar',
			updatedData: data,
		} );

		// reset
		state = settingsReducer( state, setUpdatedData( data, true ) );

		expect( state ).toStrictEqual( {
			foo: 'bar',
			updatedData: {},
		} );
	} );
} );
