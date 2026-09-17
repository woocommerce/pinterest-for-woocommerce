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
import { dispatch } from '@wordpress/data';
import { fireEvent, render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SetupAccount from './SetupAccount';
import { SETTINGS_STORE_NAME } from '../data';

describe( 'SetupAccount', () => {
	beforeEach( () => {
		wcSettings.pinterest_for_woocommerce.apiRoute = '/pinterest/v1';
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
} );
