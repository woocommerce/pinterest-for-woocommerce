jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn(),
} ) );

// Data controls may resolve a different installed copy of api-fetch.
jest.mock( '@wordpress/data-controls', () => {
	const actual = jest.requireActual( '@wordpress/data-controls' );
	return {
		...actual,
		controls: {
			...actual.controls,
			API_FETCH: ( { request } ) =>
				jest.requireMock( '@wordpress/api-fetch' ).default( request ),
		},
	};
} );

/**
 * External dependencies
 */
import '@testing-library/jest-dom';
import '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';
import { dispatch, select } from '@wordpress/data';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import SetupTracking from './SetupTracking';
import { SETTINGS_STORE_NAME } from '../data';
import { API_ENDPOINT, OPTIONS_NAME } from '../data/settings/constants';

const existingSettings = {
	tracking_advertiser: '123',
	tracking_tag: '457',
	track_conversions: false,
	account_data: { id: '321', username: 'Test merchant' },
};

describe( 'SetupTracking', () => {
	beforeEach( () => {
		wcSettings.pinterest_for_woocommerce.apiRoute = '/pinterest/v1';
		dispatch( SETTINGS_STORE_NAME ).receiveSettings( existingSettings );
		dispatch( SETTINGS_STORE_NAME ).finishResolution( 'getSettings', [] );
		apiFetch.mockImplementation( async ( { path, method, data } ) => {
			if ( path === API_ENDPOINT && method === 'POST' ) {
				return data;
			}
			if ( path === '/pinterest/v1/tagowners/?terms_agreed=false' ) {
				return {
					advertisers: [
						{ id: '123', name: 'First advertiser' },
						{ id: '789', name: 'Second advertiser' },
					],
				};
			}
			if ( path === '/pinterest/v1/tags/?advrtsr_id=123' ) {
				return {
					456: { id: '456', name: 'First tag' },
					457: { id: '457', name: 'Saved tag' },
				};
			}
			if ( path === '/pinterest/v1/tags/?advrtsr_id=789' ) {
				return { 987: { id: '987', name: 'Second advertiser tag' } };
			}
			throw new Error( `Unexpected request: ${ path }` );
		} );
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'keeps a configured tag instead of replacing it with the first tag', async () => {
		const { rerender } = render( <SetupTracking /> );

		expect(
			await screen.findByRole( 'combobox', { name: 'Tracking Tag' } )
		).toHaveValue( '457' );
		expect(
			screen.getByRole( 'combobox', { name: 'Advertiser' } )
		).toHaveValue( '123' );
		expect( select( SETTINGS_STORE_NAME ).getSettings() ).toEqual(
			existingSettings
		);

		rerender( <SetupTracking /> );
		expect( apiFetch ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'saves the first advertiser and tag during setup without losing other settings', async () => {
		dispatch( SETTINGS_STORE_NAME ).receiveSettings( {
			tracking_advertiser: undefined,
			tracking_tag: undefined,
		} );
		const { rerender } = render( <SetupTracking view="wizard" /> );

		await waitFor( () => {
			expect(
				screen.getByRole( 'combobox', { name: 'Advertiser' } )
			).toHaveValue( '123' );
			expect(
				screen.getByRole( 'combobox', { name: 'Tracking Tag' } )
			).toHaveValue( '456' );
			expect(
				screen.getByRole( 'button', { name: 'Complete Setup' } )
			).toBeEnabled();
		} );
		const savedSettings = { ...existingSettings, tracking_tag: '456' };
		expect( select( SETTINGS_STORE_NAME ).getSettings() ).toEqual(
			savedSettings
		);
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: API_ENDPOINT,
			method: 'POST',
			data: { [ OPTIONS_NAME ]: savedSettings },
		} );

		rerender( <SetupTracking view="wizard" /> );
		expect( apiFetch ).toHaveBeenCalledTimes( 4 );
	} );

	it( 'selects a valid tag when changing advertisers and preserves other settings', async () => {
		render( <SetupTracking /> );
		await screen.findByRole( 'combobox', { name: 'Tracking Tag' } );

		userEvent.selectOptions(
			screen.getByRole( 'combobox', { name: 'Advertiser' } ),
			'789'
		);

		await waitFor( () => {
			expect(
				screen.getByRole( 'combobox', { name: 'Advertiser' } )
			).toHaveValue( '789' );
			expect(
				screen.getByRole( 'combobox', { name: 'Tracking Tag' } )
			).toHaveValue( '987' );
		} );
		expect( select( SETTINGS_STORE_NAME ).getSettings() ).toEqual( {
			...existingSettings,
			tracking_advertiser: '789',
			tracking_tag: '987',
		} );
		expect( apiFetch ).toHaveBeenCalledTimes( 3 );
	} );
} );
