jest.mock( '../helpers/effects', () => {
	return {
		useCreateNotice: () => () => {},
		useSettingsSelect: () => {
			return false;
		},
		useSettingsDispatch: () => () => {},
	};
} );

jest.mock( '@woocommerce/tracks', () => {
	return {
		recordEvent: jest.fn(),
	};
} );

jest.mock( '@wordpress/api-fetch', () => {
	return {
		__esModule: true,
		default: jest.fn(),
	};
} );

/**
 * External dependencies
 */
import { recordEvent } from '@woocommerce/tracks';
import apiFetch from '@wordpress/api-fetch';
import { fireEvent, render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ClaimWebsite from './ClaimWebsite';

async function sleep( ms ) {
	return new Promise( ( resolve ) => setTimeout( resolve, ms ) );
}

describe( 'Claim Website Record Events', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'pfw_domain_verify_failure is called on domain verification failure', () => {
		apiFetch.mockImplementation( () => {
			throw 'Ups';
		} );

		const { getByText } = render(
			<ClaimWebsite goToNextStep={ () => {} } view="wizard" />
		);

		fireEvent.click( getByText( 'Start verification' ) );
		expect( recordEvent ).toHaveBeenCalledWith(
			'pfw_domain_verify_failure',
			expect.any( Object )
		);
	} );

	it( '`pfw_domain_verify_success` is fired when a site is successfully verified', async () => {
		apiFetch.mockImplementation( () => {
			return { account_data: { id: 'foo' } };
		} );

		const { getByText } = render(
			<ClaimWebsite goToNextStep={ () => {} } view="wizard" />
		);

		fireEvent.click( getByText( 'Start verification' ) );

		// Wait for async click handler and apiFetch resolution.
		const delay = sleep( 1 );
		jest.runAllTimers();
		await delay;

		expect( recordEvent ).toHaveBeenCalledWith(
			'pfw_domain_verify_success'
		);
	} );
} );
