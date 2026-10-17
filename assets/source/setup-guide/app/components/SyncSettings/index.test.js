/**
 * External dependencies
 */
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { createRegistry, RegistryProvider } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import {
	act,
	fireEvent,
	render,
	screen,
	waitFor,
} from '@testing-library/react';

/**
 * Internal dependencies
 */
import SyncSettings from '.';
import { STORE_NAME } from '../../data/settings/constants';
import { controls } from '../../data/settings/controls';
import reducer from '../../data/settings/reducer';
import * as actions from '../../data/settings/actions';
import * as selectors from '../../data/settings/selectors';

const SYNC_LABEL = 'Sync to get latest settings from Pinterest Ads Manager';

/*
 * The sync action reaches the REST API through the `@wordpress/data-controls`
 * control, which bundles its own `@wordpress/api-fetch`. Faking `window.fetch`
 * keeps the store, the control and the request handling real.
 *
 * The success notice is left out on purpose: the component removes that notice
 * on every render, so whether it is still there depends on render timing.
 */
const originalFetch = window.fetch;

beforeEach( () => {
	window.fetch = vi.fn();
} );

afterEach( () => {
	window.fetch = originalFetch;
} );

const jsonResponse = ( body, status = 200 ) => ( {
	status,
	json: () => Promise.resolve( body ),
} );

const expectSyncRequests = ( count ) => {
	expect( window.fetch ).toHaveBeenCalledTimes( count );
	const [ url, options ] = window.fetch.mock.calls[ count - 1 ];
	expect( new URL( url, 'https://example.test' ).pathname ).toBe(
		'/pinterest/v1/sync_settings/'
	);
	expect( options.method ).toBe( 'GET' );
};

/**
 * Mount the component on the real settings and notices stores; only the transport is faked.
 */
const mount = ( settings = {} ) => {
	const registry = createRegistry();
	// No resolvers: the settings page loads the settings before it shows this component.
	registry.registerStore( STORE_NAME, {
		reducer,
		actions,
		selectors,
		controls,
	} );
	registry.register( noticesStore );
	registry.dispatch( STORE_NAME ).receiveSettings( settings );

	const ui = (
		<RegistryProvider value={ registry }>
			<SyncSettings />
		</RegistryProvider>
	);

	return {
		ui,
		...render( ui ),
		getNotices: () => registry.select( noticesStore ).getNotices(),
	};
};

const deferred = () => {
	let resolve;
	const promise = new Promise( ( res ) => {
		resolve = res;
	} );
	return { promise, resolve };
};

it( 'syncs once on mount, stays busy until the response and shows the refreshed sync time', async () => {
	const initialSync = deferred();
	const manualSync = deferred();
	window.fetch
		.mockReturnValueOnce( initialSync.promise )
		.mockReturnValueOnce( manualSync.promise );

	const { ui, rerender } = mount();

	expectSyncRequests( 1 );
	expect( screen.getByText( /Syncing settings/ ) ).toBeTruthy();
	expect( screen.queryByRole( 'button', { name: SYNC_LABEL } ) ).toBeNull();
	expect( screen.getByText( /Settings last updated: - •/ ) ).toBeTruthy();

	rerender( ui );
	expect( window.fetch ).toHaveBeenCalledTimes( 1 );

	await act( async () => {
		initialSync.resolve(
			jsonResponse( {
				success: true,
				synced_settings: {
					automatic_enhanced_match_support: true,
					last_synced_settings: '13 Oct 2026, 10:00:00 am',
				},
			} )
		);
	} );

	expect(
		await screen.findByText(
			/Settings last updated: 13 Oct 2026, 10:00:00 am •/
		)
	).toBeTruthy();
	expect( screen.queryByText( /Syncing settings/ ) ).toBeNull();
	// Leaving the busy state and receiving the settings re-rendered the component.
	expect( window.fetch ).toHaveBeenCalledTimes( 1 );

	fireEvent.click( screen.getByRole( 'button', { name: SYNC_LABEL } ) );

	expectSyncRequests( 2 );
	expect( screen.getByText( /Syncing settings/ ) ).toBeTruthy();

	await act( async () => {
		manualSync.resolve(
			jsonResponse( {
				success: true,
				synced_settings: {
					last_synced_settings: '13 Oct 2026, 11:30:00 am',
				},
			} )
		);
	} );

	expect(
		await screen.findByText(
			/Settings last updated: 13 Oct 2026, 11:30:00 am •/
		)
	).toBeTruthy();
	expect( screen.getByRole( 'button', { name: SYNC_LABEL } ) ).toBeTruthy();
} );

it( 'reports a failed sync, keeps the sync time and recovers through the Retry action', async () => {
	const retriedSync = deferred();
	window.fetch
		.mockResolvedValueOnce(
			jsonResponse(
				{ code: 'rest_forbidden', message: 'Sorry, not allowed.' },
				403
			)
		)
		.mockReturnValueOnce( retriedSync.promise );

	const { getNotices } = mount( {
		last_synced_settings: '12 Oct 2026, 09:00:00 am',
	} );

	// The sync link comes back, so the merchant is not left on a spinner.
	expect(
		await screen.findByRole( 'button', { name: SYNC_LABEL } )
	).toBeTruthy();
	expect(
		screen.getByText( /Settings last updated: 12 Oct 2026, 09:00:00 am •/ )
	).toBeTruthy();

	await waitFor( () => expect( getNotices() ).toHaveLength( 1 ) );
	const [ failure ] = getNotices();
	expect( failure ).toMatchObject( {
		status: 'error',
		content: 'Failed to sync settings with Pinterest Ads Manager.',
	} );
	expect( failure.actions.map( ( { label } ) => label ) ).toEqual( [
		'Retry.',
	] );
	expect( window.fetch ).toHaveBeenCalledTimes( 1 );

	await act( async () => {
		failure.actions[ 0 ].onClick();
	} );

	expectSyncRequests( 2 );
	expect( screen.getByText( /Syncing settings/ ) ).toBeTruthy();

	await act( async () => {
		retriedSync.resolve(
			jsonResponse( {
				success: true,
				synced_settings: {
					last_synced_settings: '13 Oct 2026, 10:05:00 am',
				},
			} )
		);
	} );

	expect(
		await screen.findByText(
			/Settings last updated: 13 Oct 2026, 10:05:00 am •/
		)
	).toBeTruthy();
	expect( screen.getByRole( 'button', { name: SYNC_LABEL } ) ).toBeTruthy();
} );

it( 'reports a sync that the server answers with success: false as failed', async () => {
	window.fetch.mockResolvedValueOnce( jsonResponse( { success: false } ) );

	const { getNotices } = mount();

	await waitFor( () => expect( getNotices() ).toHaveLength( 1 ) );
	expect( getNotices()[ 0 ] ).toMatchObject( {
		status: 'error',
		content: 'Failed to sync settings with Pinterest Ads Manager.',
	} );
} );
