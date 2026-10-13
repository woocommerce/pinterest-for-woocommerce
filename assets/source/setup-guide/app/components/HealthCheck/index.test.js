/**
 * External dependencies
 */
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { createRegistry, RegistryProvider } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { act, render, screen, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import HealthCheck from '.';
import { STORE_NAME } from '../../data/settings/constants';
import { controls } from '../../data/settings/controls';
import reducer from '../../data/settings/reducer';
import * as actions from '../../data/settings/actions';
import * as selectors from '../../data/settings/selectors';

vi.mock( '@wordpress/api-fetch', () => ( {
	default: vi.fn(),
} ) );

const { pinterestLinks } = wcSettings.pinterest_for_woocommerce;

beforeEach( () => {
	wcSettings.pinterest_for_woocommerce.pinterestLinks = {
		...pinterestLinks,
		// The link the plugin localizes for the settings screens.
		appealDeclinedMerchant:
			'https://www.pinterest.com/product-catalogs/data-source/?showModal=true',
	};
} );

afterEach( () => {
	wcSettings.pinterest_for_woocommerce.pinterestLinks = pinterestLinks;
} );

/**
 * Mount the component on the real settings and notices stores; only the transport is faked.
 */
const mount = ( settings = {} ) => {
	const registry = createRegistry();
	// No resolvers, so the health request is the only one made.
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
			<HealthCheck />
		</RegistryProvider>
	);

	return {
		ui,
		registry,
		...render( ui ),
		getNotices: () => registry.select( noticesStore ).getNotices(),
	};
};

it( 'checks the account health once and stays silent for an approved account', async () => {
	apiFetch.mockResolvedValue( { status: 'approved' } );

	const { ui, registry, rerender, container, getNotices } = mount();

	rerender( ui );
	// A settings change re-renders the component as well; this also flushes the response.
	await act( async () => {
		registry
			.dispatch( STORE_NAME )
			.receiveSettings( { track_conversions: true } );
	} );

	expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	expect( apiFetch ).toHaveBeenCalledWith( {
		path: '/pinterest/v1/health/',
		method: 'GET',
	} );
	expect( container.innerHTML ).toBe( '' );
	expect( getNotices() ).toEqual( [] );
} );

it( 'shows a disapproved account the known reasons and an appeal link for its advertiser', async () => {
	apiFetch.mockResolvedValue( {
		status: 'declined',
		reasons: [ 'PROHIBITED_PRODUCTS', 'NOT_A_KNOWN_REASON', 'SERVICES' ],
	} );

	const { getNotices } = mount( { tracking_advertiser: '549755885175' } );

	expect(
		// The notice is also announced through the `@wordpress/a11y` live region.
		await screen.findByText( 'Your merchant account is disapproved.', {
			ignore: 'script, style, .a11y-speak-region',
		} )
	).toBeTruthy();
	expect(
		screen.getAllByRole( 'listitem' ).map( ( item ) => item.textContent )
	).toEqual( [
		'Merchant does not meet our policy on prohibited products',
		'Merchant offers services rather than products',
	] );
	expect(
		screen.getByRole( 'link', { name: 'Submit an appeal' } ).href
	).toBe(
		'https://www.pinterest.com/product-catalogs/data-source/?showModal=true&advertiserId=549755885175'
	);
	expect( getNotices() ).toEqual( [] );
} );

it.each( [
	[
		'the reason sent by the server',
		{ code: 'rest_forbidden', message: 'Sorry, not allowed.' },
		'Sorry, not allowed.',
	],
	[
		'a fallback when the failure has no message',
		{},
		'Couldn’t retrieve the health status of your account.',
	],
] )(
	'tells the merchant a failed health check with %s',
	async ( name, failure, expectedMessage ) => {
		apiFetch.mockRejectedValue( failure );

		const { container, getNotices } = mount();

		await waitFor( () =>
			expect( getNotices() ).toEqual( [
				expect.objectContaining( {
					status: 'error',
					content: expectedMessage,
				} ),
			] )
		);
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect( container.innerHTML ).toBe( '' );
	}
);
