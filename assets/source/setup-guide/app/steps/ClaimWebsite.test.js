jest.mock( '../helpers/effects', () => {
	return {
		useCreateNotice: () => () => {},
		useSettingsSelect: () => {
			return false;
		},
		useSettingsDispatch: () => () => {},
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
import { render, waitFor } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ClaimWebsite from './ClaimWebsite';

describe( 'Claim Website Record Events', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'pfw_domain_verify_failure is called on domain verification failure', () => {
		apiFetch.mockImplementation( () => {
			throw 'Ups';
		} );

		render( <ClaimWebsite goToNextStep={ () => {} } view="wizard" /> );

		expect( recordEvent ).toHaveBeenCalledWith(
			'pfw_domain_verify_failure',
			expect.any( Object )
		);
	} );

	it( '`pfw_domain_verify_success` is fired when a site is successfully verified', async () => {
		apiFetch.mockImplementation( () => {
			return { account_data: { id: 'foo' } };
		} );

		render( <ClaimWebsite goToNextStep={ () => {} } view="wizard" /> );

		// Wait for async click handler and apiFetch resolution.
		await waitFor( () =>
			expect( recordEvent ).toHaveBeenCalledWith(
				'pfw_domain_verify_success'
			)
		);
	} );
} );
