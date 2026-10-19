/** External dependencies */
import { expect, test, vi } from 'vitest';
import { createRegistry } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

/** Internal dependencies */
import reducer from './reducer';
import * as actions from './actions';
import * as selectors from './selectors';
import * as resolvers from './resolvers';
import { controls } from './controls';
import { STORE_NAME } from './constants';

vi.mock( '@wordpress/api-fetch', () => ( { default: vi.fn() } ) );

test( 'interaction failures settle without opening prompts and can be retried', async () => {
	let state;
	const registry = createRegistry();
	registry.registerStore( STORE_NAME, {
		reducer: ( ...args ) => {
			state = reducer( ...args );
			return state;
		},
		actions,
		selectors,
		resolvers,
		controls,
	} );
	const error = { message: 'Offline' };
	apiFetch.mockRejectedValueOnce( error );
	await registry.resolveSelect( STORE_NAME ).getUserInteractions();
	expect( state.isRequesting ).toBe( false );
	expect( state.requestingErrors.user_interaction ).toEqual( error );
	expect( registry.select( STORE_NAME ).areInteractionsLoaded() ).toBe(
		false
	);
	apiFetch.mockResolvedValueOnce( { ads_modal_dismissed: true } );
	registry
		.dispatch( STORE_NAME )
		.invalidateResolution( 'getUserInteractions', [] );
	await registry.resolveSelect( STORE_NAME ).getUserInteractions();
	expect( state.isRequesting ).toBe( false );
	expect( state.requestingErrors.user_interaction ).toBe( false );
	expect( registry.select( STORE_NAME ).areInteractionsLoaded() ).toBe(
		true
	);
	expect( apiFetch ).toHaveBeenCalledTimes( 2 );
} );
