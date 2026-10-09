/**
 * External dependencies
 */
import { expect, it, vi } from 'vitest';
import { createRegistry, RegistryProvider } from '@wordpress/data';
import {
	act,
	fireEvent,
	render,
	screen,
	waitFor,
} from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import SettingsApp from './SettingsApp';
import { OPTIONS_NAME, STORE_NAME } from '../data/settings/constants';
import { controls } from '../data/settings/controls';
import reducer from '../data/settings/reducer';
import * as actions from '../data/settings/actions';
import * as selectors from '../data/settings/selectors';
import * as resolvers from '../data/settings/resolvers';

vi.mock( '@wordpress/api-fetch', () => ( {
	default: vi.fn(),
} ) );

vi.mock( '../helpers/effects', async () => ( {
	...( await vi.importActual( '../helpers/effects' ) ),
	useBodyClasses: () => {},
	useCreateNotice: () => () => {},
} ) );
vi.mock( '../components/HealthCheck', () => ( { default: () => null } ) );
vi.mock( '../../../components/navigation-classic', () => ( {
	default: () => null,
} ) );
vi.mock( '../components/SyncSettings', () => ( { default: () => null } ) );
vi.mock( '../steps/SetupProductSync', () => ( { default: () => null } ) );
vi.mock( '../steps/SetupPins', () => ( { default: () => null } ) );
vi.mock( '../steps/AdvancedSettings', () => ( {
	default: () => <input aria-label="Enable Debug Logging" />,
} ) );
vi.mock( '../components/SaveSettingsButton', () => ( {
	default: () => <button>Save changes</button>,
} ) );

it( 'keeps editing unavailable until full settings load, even after partial updates', async () => {
	let finishRequest;
	const response = new Promise( ( resolve ) => {
		finishRequest = resolve;
	} );
	let finishRefresh;
	const refresh = new Promise( ( resolve ) => {
		finishRefresh = resolve;
	} );
	const fetch = vi
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

it( 'shows a failed settings load and lets the merchant retry before editing', async () => {
	apiFetch
		.mockRejectedValueOnce( new Error( 'Settings request failed' ) )
		.mockResolvedValueOnce( {
			[ OPTIONS_NAME ]: { tracking_tag: 'saved-tag' },
		} );
	const registry = createRegistry();
	registry.registerStore( STORE_NAME, {
		reducer,
		actions,
		selectors,
		resolvers,
		controls,
	} );
	render(
		<RegistryProvider value={ registry }>
			<SettingsApp />
		</RegistryProvider>
	);
	expect(
		// The notice is also announced through the `@wordpress/a11y` live region.
		await screen.findByText(
			'Could not load your Pinterest settings. Please try again.',
			{ ignore: 'script, style, .a11y-speak-region' }
		)
	).toBeTruthy();
	expect(
		screen.queryByRole( 'button', { name: 'Save changes' } )
	).toBeNull();
	fireEvent.click( screen.getByRole( 'button', { name: 'Retry' } ) );
	expect(
		await screen.findByRole( 'button', { name: 'Save changes' } )
	).toBeTruthy();
	expect( screen.queryByRole( 'button', { name: 'Retry' } ) ).toBeNull();
	expect( registry.select( STORE_NAME ).getSettings() ).toEqual( {
		tracking_tag: 'saved-tag',
	} );
	expect( apiFetch ).toHaveBeenCalledTimes( 2 );
} );
