jest.mock( '@wordpress/data', () => ( {
	select: jest.fn(),
	dispatch: jest.fn(),
} ) );
jest.mock( '../../../../catalog-sync/data', () => ( {
	REPORTS_STORE_NAME: 'test/reports',
} ) );

import { select, dispatch } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';
import reducer from './reducer';
import * as actions from './actions';
import * as selectors from './selectors';
import { API_ENDPOINT, OPTIONS_NAME, STORE_NAME } from './constants';
import { getSettings } from './resolvers';

describe( 'settings state', () => {
	afterEach( () => jest.resetAllMocks() );

	it.each( [
		[ 'missing', undefined, { track_conversions: true, merchant: 'keep' } ],
		[ 'empty', {}, { track_conversions: true, merchant: 'keep' } ],
		[
			'populated',
			{ track_conversions: false, tracking_tag: 'tag-2' },
			{
				track_conversions: false,
				merchant: 'keep',
				tracking_tag: 'tag-2',
			},
		],
	] )(
		'merges %s settings without losing unrelated values',
		( name, input, expected ) => {
			const initial = reducer(
				undefined,
				actions.receiveSettings( {
					track_conversions: true,
					merchant: 'keep',
				} )
			);
			const next = reducer( initial, actions.receiveSettings( input ) );
			expect( selectors.getSettings( next ) ).toEqual( expected );
			expect( selectors.getSetting( next, 'merchant' ) ).toBe( 'keep' );
			expect( initial.settings ).toEqual( {
				track_conversions: true,
				merchant: 'keep',
			} );
		}
	);

	it( 'starts idle with no errors or configured tracking', () => {
		const state = reducer( undefined, {} );
		expect( selectors.getSettings( state ) ).toEqual( {} );
		expect( selectors.isSettingsUpdating( state ) ).toBe( false );
		expect( selectors.isSettingsSyncing( state ) ).toBe( false );
		expect( selectors.getSettingsUpdatingError( state ) ).toBe( false );
		expect( selectors.getSettingsRequestingError( state, 'all' ) ).toBe(
			false
		);
		expect( selectors.isTrackingConfigured( state ) ).toBe( false );
	} );

	it.each( [
		[ {}, false ],
		[ { tracking_advertiser: 'advertiser' }, false ],
		[ { tracking_tag: 'tag' }, false ],
		[ { tracking_advertiser: '', tracking_tag: 'tag' }, false ],
		[ { tracking_advertiser: 'advertiser', tracking_tag: 'tag' }, true ],
	] )( 'requires both tracking identifiers: %j', ( settings, expected ) => {
		expect( selectors.isTrackingConfigured( { settings } ) ).toBe(
			expected
		);
	} );

	it( 'saves the complete settings state and finishes updating after success', () => {
		const settings = { track_conversions: false, tracking_tag: 'tag' };
		const reports = {
			resetFeed: jest.fn(),
			invalidateResolutionForStore: jest.fn(),
		};
		select.mockReturnValue( { getSettings: () => settings } );
		dispatch.mockReturnValue( reports );
		const save = actions.updateSettings(
			{ track_conversions: false },
			true
		);
		expect( save.next().value ).toEqual(
			actions.receiveSettings( { track_conversions: false } )
		);
		let state = reducer( undefined, save.next().value );
		expect( selectors.isSettingsUpdating( state ) ).toBe( true );
		save.next();
		expect( select ).toHaveBeenCalledWith( STORE_NAME );
		expect( save.next( settings ).value ).toEqual(
			apiFetch( {
				path: API_ENDPOINT,
				method: 'POST',
				data: { [ OPTIONS_NAME ]: settings },
			} )
		);
		state = reducer( state, save.next( { [ OPTIONS_NAME ]: true } ).value );
		expect( selectors.isSettingsUpdating( state ) ).toBe( false );
		expect( save.next() ).toEqual( {
			done: true,
			value: { success: true },
		} );
		expect( reports.resetFeed ).toHaveBeenCalledTimes( 1 );
		expect( reports.invalidateResolutionForStore ).toHaveBeenCalledTimes(
			1
		);
	} );

	it( 'records a failed save, clears the busy state and rejects', () => {
		select.mockReturnValue( { getSettings: () => ( {} ) } );
		const error = new Error( 'Save failed' );
		const save = actions.updateSettings( {}, true );
		save.next();
		let state = reducer( undefined, save.next().value );
		save.next();
		save.next( {} );
		state = reducer( state, save.throw( error ).value );
		expect( selectors.getSettingsUpdatingError( state ) ).toBe( error );
		expect( selectors.isSettingsUpdating( state ) ).toBe( false );
		expect( () => save.next() ).toThrow( error );
		expect( dispatch ).not.toHaveBeenCalled();
	} );

	it( 'updates local values without requesting a save', () => {
		const save = actions.updateSettings( { tracking_tag: '' } );
		expect( save.next().value ).toEqual(
			actions.receiveSettings( { tracking_tag: '' } )
		);
		expect( save.next() ).toEqual( {
			done: true,
			value: { success: true },
		} );
		expect( select ).not.toHaveBeenCalled();
	} );

	it( 'stores load failures under the requested settings key', () => {
		const request = getSettings();
		const error = new Error( 'Load failed' );
		request.next();
		const state = reducer( undefined, request.throw( error ).value );
		expect( selectors.getSettingsRequestingError( state, 'all' ) ).toBe(
			error
		);
		expect( selectors.getSettingsRequestingError( state, 'other' ) ).toBe(
			false
		);
	} );

	it( 'syncs returned settings and clears the loading state', () => {
		const sync = actions.syncSettings();
		let state = reducer( undefined, sync.next().value );
		expect( selectors.isSettingsSyncing( state ) ).toBe( true );
		expect( sync.next().value ).toEqual(
			apiFetch( {
				path: '/pinterest/v1/sync_settings/',
				method: 'GET',
			} )
		);
		state = reducer(
			state,
			sync.next( {
				success: true,
				synced_settings: { tracking_tag: 'synced-tag' },
			} ).value
		);
		state = reducer( state, sync.next().value );
		expect( selectors.getSetting( state, 'tracking_tag' ) ).toBe(
			'synced-tag'
		);
		expect( selectors.isSettingsSyncing( state ) ).toBe( false );
		expect( sync.next().value ).toEqual( { success: true } );
	} );

	it( 'clears syncing after an API error', () => {
		const sync = actions.syncSettings();
		let state = reducer( undefined, sync.next().value );
		sync.next();
		const error = new Error( 'Sync failed' );
		state = reducer( state, sync.throw( error ).value );
		expect( selectors.isSettingsSyncing( state ) ).toBe( false );
		expect( () => sync.next() ).toThrow( error );
	} );
} );
