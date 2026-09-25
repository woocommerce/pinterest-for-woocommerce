/**
 * External dependencies
 */
import { recordEvent } from '@woocommerce/tracks';
import { render, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * Internal dependencies
 */
import UnsupportedCountryNotice from './index';
import { useDocumentationLinkTracking } from '../../helpers/documentation-link-props';

const Owner = ( { children } ) => {
	useDocumentationLinkTracking();
	return <div>{ children }</div>;
};

describe( 'UnsupportedCountryNotice', () => {
	it( '`pfw_get_started_notice_link_click` is tracked on click', async () => {
		const { getByText } = render(
			<UnsupportedCountryNotice countryCode="es" />,
			{ wrapper: Owner }
		);

		fireEvent.click( getByText( 'Change your store’s country here' ) );

		await waitFor( () =>
			expect( recordEvent ).toHaveBeenCalledWith(
				'pfw_get_started_notice_link_click',
				expect.any( Object )
			)
		);
	} );
} );
