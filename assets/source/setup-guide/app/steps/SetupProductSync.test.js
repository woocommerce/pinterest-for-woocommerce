jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn(),
} ) );
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
import SetupProductSync from './SetupProductSync';
import { SETTINGS_STORE_NAME } from '../data';
import { API_ENDPOINT, OPTIONS_NAME } from '../data/settings/constants';

const existingSettings = {
	product_sync_enabled: true,
	product_sync_categories: [],
	tracking_advertiser: '123',
};

describe( 'Product sync category inclusion', () => {
	beforeEach( () => {
		dispatch( SETTINGS_STORE_NAME ).receiveSettings( existingSettings );
		dispatch( SETTINGS_STORE_NAME ).finishResolution( 'getSettings', [] );
		apiFetch.mockImplementation( async ( { path, method, data } ) => {
			if ( path === API_ENDPOINT && method === 'POST' ) {
				return data;
			}
			if ( path.startsWith( '/wc-analytics/products/categories?' ) ) {
				return [ { id: 42, name: 'Clothing', slug: 'clothing' } ];
			}
			throw new Error( `Unexpected request: ${ path }` );
		} );
	} );

	afterEach( () => jest.clearAllMocks() );

	it( 'selects categories through native search and saves without replacing other settings', async () => {
		const { unmount } = render( <SetupProductSync view="wizard" /> );
		userEvent.type(
			screen.getByRole( 'combobox', {
				name: 'Search product categories',
			} ),
			'Cloth'
		);
		userEvent.click( await screen.findByRole( 'option' ) );
		const selected = [ { key: 42, label: 'Clothing' } ];
		await waitFor( () =>
			expect( apiFetch ).toHaveBeenCalledWith( {
				path: API_ENDPOINT,
				method: 'POST',
				data: {
					[ OPTIONS_NAME ]: {
						...existingSettings,
						product_sync_categories: selected,
					},
				},
			} )
		);
		expect(
			select( SETTINGS_STORE_NAME ).getSettings().product_sync_categories
		).toEqual( selected );
		unmount();
		render( <SetupProductSync view="wizard" /> );
		expect( screen.getByText( 'Clothing' ) ).toBeVisible();
		userEvent.click(
			screen.getByRole( 'button', { name: 'Remove Clothing' } )
		);
		await waitFor( () =>
			expect( select( SETTINGS_STORE_NAME ).getSettings() ).toEqual(
				existingSettings
			)
		);
	} );

	it( 'retains the selection while sync is disabled and stages changes on the settings page', () => {
		dispatch( SETTINGS_STORE_NAME ).receiveSettings( {
			product_sync_enabled: false,
			product_sync_categories: [ { key: 42, label: 'Clothing' } ],
		} );
		render( <SetupProductSync view="settings" /> );
		expect(
			screen.queryByRole( 'combobox', {
				name: 'Search product categories',
			} )
		).not.toBeInTheDocument();
		userEvent.click(
			screen.getByRole( 'checkbox', { name: 'Enable Product Sync' } )
		);
		expect(
			screen.getByRole( 'combobox', {
				name: 'Search product categories',
			} )
		).toBeEnabled();
		expect(
			select( SETTINGS_STORE_NAME ).getSettings().product_sync_categories
		).toEqual( [ { key: 42, label: 'Clothing' } ] );
		expect( apiFetch ).not.toHaveBeenCalled();
	} );
} );
