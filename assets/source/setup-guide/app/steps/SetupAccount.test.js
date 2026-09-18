jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn(),
} ) );

/**
 * External dependencies
 */
import '@testing-library/jest-dom';
import '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';
import { dispatch, select } from '@wordpress/data';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SetupAccount from './SetupAccount';
import { SETTINGS_STORE_NAME } from '../data';

describe( 'SetupAccount', () => {
	beforeEach( () => {
		apiFetch.mockReset();
		select( 'core/notices' )
			.getNotices()
			.forEach( ( notice ) => {
				dispatch( 'core/notices' ).removeNotice( notice.id );
			} );
		wcSettings.pinterest_for_woocommerce.businessAccounts = [];
		dispatch( SETTINGS_STORE_NAME ).receiveSettings( {
			account_data: { id: '123', username: 'Test merchant' },
		} );
		dispatch( SETTINGS_STORE_NAME ).finishResolution( 'getSettings', [] );
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'renders a connected account on the first render', () => {
		render(
			<SetupAccount
				view="settings"
				isConnected={ true }
				isBusinessConnected={ true }
			/>
		);

		expect( screen.getByText( 'Test merchant' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Disconnect' } )
		).toBeInTheDocument();
	} );

	it( 'refreshes missing businesses on focus and stops once one is found', async () => {
		apiFetch.mockResolvedValue( [
			{ value: '456', label: 'Test business' },
		] );
		render(
			<SetupAccount
				view="settings"
				isConnected={ true }
				isBusinessConnected={ false }
			/>
		);

		expect(
			screen.getByText( 'No business account detected' )
		).toBeInTheDocument();
		fireEvent.focus( window );

		expect(
			await screen.findByRole( 'option', { name: 'Test business' } )
		).toBeInTheDocument();
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/pinterest/v1/businesses/',
			method: 'GET',
		} );

		fireEvent.focus( window );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'keeps the account connected after an API failure and retries on focus', async () => {
		apiFetch
			.mockRejectedValueOnce( new Error( 'Business lookup failed' ) )
			.mockResolvedValueOnce( [
				{ value: '789', label: 'Retry business' },
			] );
		render(
			<SetupAccount
				view="settings"
				isConnected={ true }
				isBusinessConnected={ false }
			/>
		);
		fireEvent.focus( window );
		await waitFor( () =>
			expect( select( 'core/notices' ).getNotices() ).toEqual(
				expect.arrayContaining( [
					expect.objectContaining( {
						status: 'error',
						content: 'Business lookup failed',
					} ),
				] )
			)
		);
		expect(
			screen.getByRole( 'button', { name: 'Disconnect' } )
		).toBeInTheDocument();
		expect( screen.queryByRole( 'combobox' ) ).not.toBeInTheDocument();
		fireEvent.focus( window );
		expect(
			await screen.findByRole( 'option', { name: 'Retry business' } )
		).toBeInTheDocument();
		expect( apiFetch ).toHaveBeenCalledTimes( 2 );
	} );
} );
