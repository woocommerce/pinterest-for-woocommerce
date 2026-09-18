/**
 * External dependencies
 */
import { createRegistry, RegistryProvider } from '@wordpress/data';
import { act, render, screen, waitFor } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SettingsApp from './SettingsApp';
import { STORE_NAME } from '../data/settings/constants';
import reducer from '../data/settings/reducer';
import * as actions from '../data/settings/actions';
import * as selectors from '../data/settings/selectors';
import * as resolvers from '../data/settings/resolvers';

jest.mock( '../helpers/effects', () => ( {
	...jest.requireActual( '../helpers/effects' ),
	useBodyClasses: () => {},
	useCreateNotice: () => () => {},
} ) );
jest.mock( '../components/HealthCheck', () => () => null );
jest.mock( '../../../components/navigation-classic', () => () => null );
jest.mock( '../components/SyncSettings', () => () => null );
jest.mock( '../steps/SetupProductSync', () => () => null );
jest.mock( '../steps/SetupPins', () => () => null );
jest.mock( '../steps/AdvancedSettings', () => () => (
	<input aria-label="Enable Debug Logging" />
) );
jest.mock( '../components/SaveSettingsButton', () => () => (
	<button>Save changes</button>
) );

it( 'keeps editing unavailable until full settings load, even after partial updates', async () => {
	let finishRequest;
	const response = new Promise( ( resolve ) => {
		finishRequest = resolve;
	} );
	let finishRefresh;
	const refresh = new Promise( ( resolve ) => {
		finishRefresh = resolve;
	} );
	const fetch = jest
		.fn()
		.mockReturnValueOnce( response )
		.mockReturnValueOnce( refresh );
	const registry = createRegistry();
	registry.registerStore( STORE_NAME, {
		reducer,
		actions,
		selectors,
		resolvers,
		controls: { FETCH: fetch },
	} );
	render(
		<RegistryProvider value={ registry }>
			<SettingsApp />
		</RegistryProvider>
	);
	await act( async () => {
		registry.dispatch( STORE_NAME ).receiveSettings( {
			automatic_enhanced_match_support: false,
		} );
	} );
	expect(
		screen.queryByRole( 'button', { name: 'Save changes' } )
	).toBeNull();
	expect( screen.queryByLabelText( 'Enable Debug Logging' ) ).toBeNull();
	await act( async () => {
		finishRequest( {
			rich_pins_on_products: true,
			tracking_tag: 'saved-tag',
		} );
		await response;
	} );
	expect(
		await screen.findByRole( 'button', { name: 'Save changes' } )
	).toBeTruthy();
	expect( screen.getByLabelText( 'Enable Debug Logging' ) ).toBeTruthy();
	expect( registry.select( STORE_NAME ).getSettings() ).toEqual( {
		automatic_enhanced_match_support: false,
		rich_pins_on_products: true,
		tracking_tag: 'saved-tag',
	} );
	const saveButton = screen.getByRole( 'button', { name: 'Save changes' } );
	await act( async () => {
		registry
			.dispatch( STORE_NAME )
			.invalidateResolution( 'getSettings', [] );
	} );
	await waitFor( () => expect( fetch ).toHaveBeenCalledTimes( 2 ) );
	// Saving invalidates the resolver; keep the same form and its child state while it reloads.
	expect( screen.getByRole( 'button', { name: 'Save changes' } ) ).toBe(
		saveButton
	);
	await act( async () => {
		finishRefresh( {
			rich_pins_on_products: true,
			tracking_tag: 'saved-tag',
		} );
		await refresh;
	} );
	expect( screen.getByRole( 'button', { name: 'Save changes' } ) ).toBe(
		saveButton
	);
} );
