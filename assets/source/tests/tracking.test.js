jest.mock( '@woocommerce/tracks', () => {
	return {
		recordEvent: jest.fn(),
	};
} );

jest.mock( '@woocommerce/settings', () => {
	return {
		getSetting: () => {
			return {
				es: 'Spain',
			};
		},
	};
} );

/**
 * External dependencies
 */
import { recordEvent } from '@woocommerce/tracks';
import { render, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * Internal dependencies
 */
import UnsupportedCountryNotice from '../setup-guide/app/components/UnsupportedCountryNotice';
import PrelaunchNotice from '../components/prelaunch-notice';

describe( 'Component Records Events', () => {
	it( 'UnsupportedCountryNotice records events on click', () => {
		const { getByText } = render(
			<UnsupportedCountryNotice countryCode="es" />
		);

		fireEvent.click( getByText( 'Change your store’s country here' ) );

		expect( recordEvent.mock.calls[ 0 ][ 0 ] ).toBe(
			'pfw_get_started_notice_link_click'
		);
	} );

	it( 'PrelaunchNotice records events on click', () => {
		const { getByText } = render( <PrelaunchNotice /> );

		fireEvent.click( getByText( 'Click here for more information.' ) );

		expect( recordEvent.mock.calls[ 0 ][ 0 ] ).toBe(
			'pfw_get_started_notice_link_click'
		);
	} );
} );
