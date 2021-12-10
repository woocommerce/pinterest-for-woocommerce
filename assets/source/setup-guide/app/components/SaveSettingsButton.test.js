jest.mock( '../helpers/get-context-by-path' );
jest.mock( '../helpers/effects', () => {
	return {
		useCreateNotice: () => () => {},
		useSettingsSelect: () => {},
		useSettingsDispatch: () => () => {},
	};
} );
jest.mock( '@woocommerce/tracks', () => {
	return {
		recordEvent: jest.fn(),
	};
} );

/**
 * External dependencies
 */
import { recordEvent } from '@woocommerce/tracks';
import { fireEvent, render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SaveSettingsButton from './SaveSettingsButton';

describe( 'Save Settings function', () => {
	it( 'RecordEvent is called on save', () => {
		const { getByRole } = render( <SaveSettingsButton /> );

		const saveSettingsBtn = getByRole( 'button' );

		fireEvent.click( saveSettingsBtn );
		expect( recordEvent ).toHaveBeenCalled();
	} );
} );
