/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';
import { recordEvent } from '@woocommerce/tracks';
import { render, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom/vitest';

/**
 * Internal dependencies
 */
import UnsupportedCountryNotice from './index';
import { expectKnownReactDeprecations } from '../../../../tests/known-react-deprecations';

describe( 'UnsupportedCountryNotice', () => {
	expectKnownReactDeprecations( 'Link' );

	it( '`pfw_get_started_notice_link_click` is tracked on click', () => {
		const { getByText } = render(
			<UnsupportedCountryNotice countryCode="es" />
		);

		fireEvent.click( getByText( 'Change your store’s country here' ) );

		expect( recordEvent ).toHaveBeenCalledWith(
			'pfw_get_started_notice_link_click',
			expect.any( Object )
		);
	} );
} );
