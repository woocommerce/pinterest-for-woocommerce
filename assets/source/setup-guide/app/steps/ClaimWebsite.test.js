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

describe( 'Claim Website Record Events', () => {
	it( 'pfw_domain_verify_failure is called on domain verification failure', () => {
		apiFetch.default.mockImplementation( async () => {
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
} );
