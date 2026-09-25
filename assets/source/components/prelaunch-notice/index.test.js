/**
 * External dependencies
 */
import { recordEvent } from '@woocommerce/tracks';
import { render, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * Internal dependencies
 */
import PrelaunchNotice from './index';
import { useDocumentationLinkTracking } from '../../setup-guide/app/helpers/documentation-link-props';

const Owner = ( { children } ) => {
	useDocumentationLinkTracking();
	return <div>{ children }</div>;
};

describe( 'PrelaunchNotice', () => {
	it( '`pfw_get_started_notice_link_click` is tracked on click', async () => {
		const { getByText } = render( <PrelaunchNotice />, { wrapper: Owner } );

		fireEvent.click( getByText( 'Click here for more information.' ) );

		await waitFor( () =>
			expect( recordEvent ).toHaveBeenCalledWith(
				'pfw_get_started_notice_link_click',
				expect.any( Object )
			)
		);
	} );
} );
