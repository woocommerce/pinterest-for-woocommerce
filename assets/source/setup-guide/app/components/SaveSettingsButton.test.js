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

afterEach( () => {
	jest.clearAllMocks();
} );

describe( 'Save Settings function', () => {
	const eventName = 'pfw_save_changes_button_click';

	it( `${ eventName } is called on Save settings`, () => {
		const { getByRole } = render( <SaveSettingsButton /> );
		const saveSettingsBtn = getByRole( 'button' );

		fireEvent.click( saveSettingsBtn );
		expect( recordEvent.mock.calls[ 0 ][ 0 ] ).toBe( eventName );
		expect( recordEvent.mock.calls[ 0 ][ 1 ].context ).toBe(
			'pinterest_settings'
		);
	} );
} );
