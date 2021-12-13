jest.mock( '@woocommerce/tracks', () => {
	return {
		recordEvent: jest.fn(),
	};
} );

/**
 * External dependencies
 */
import { recordEvent } from '@woocommerce/tracks';
import { render, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PrelaunchNotice from './index';

describe( 'PreLaunch Notice Component', () => {
	it( 'Record event on click', () => {
		const { getByText } = render( <PrelaunchNotice /> );
		const link = getByText( 'Click here for more information.' );
		fireEvent.click( link );

		// The first arg of the first call to the function match the expected event
		expect( recordEvent.mock.calls[ 0 ][ 0 ] ).toBe(
			'pfw_get_started_notice_link_click'
		);
	} );
} );
