jest.mock( '../helpers/effects', () => {
	return {
		useCreateNotice: jest.fn(),
		useSettingsSelect: jest.fn(),
		useSettingsDispatch: jest.fn(),
		useResetSettings: jest.fn(),
	};
} );
jest.mock( '../../../catalog-sync/helpers/effects', () => ( {
	useResetUserInteractions: jest.fn(),
} ) );
jest.mock( '../helpers/connect-advertiser', () => ( {
	__esModule: true,
	default: jest.fn(),
} ) );
jest.mock( '@woocommerce/tracks', () => {
	return {
		recordEvent: jest.fn().mockName( 'recordEvent' ),
	};
} );

/**
 * External dependencies
 */
import { recordEvent } from '@woocommerce/tracks';
import '@testing-library/jest-dom';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

/**
 * Internal dependencies
 */
import SaveSettingsButton from './SaveSettingsButton';
import {
	useCreateNotice,
	useSettingsSelect,
	useSettingsDispatch,
	useResetSettings,
} from '../helpers/effects';
import { useResetUserInteractions } from '../../../catalog-sync/helpers/effects';
import connectAdvertiser from '../helpers/connect-advertiser';

const save = jest.fn();
const notice = jest.fn();
const resetSettings = jest.fn();
const resetInteractions = jest.fn();

beforeEach( () => {
	jest.resetAllMocks();
	useCreateNotice.mockReturnValue( notice );
	useSettingsSelect.mockImplementation( ( selector ) =>
		selector === 'isSettingsUpdating' ? false : {}
	);
	useSettingsDispatch.mockReturnValue( save );
	useResetSettings.mockReturnValue( resetSettings );
	useResetUserInteractions.mockReturnValue( resetInteractions );
	save.mockResolvedValue( { success: true } );
} );

describe( 'Save Settings function', () => {
	const eventName = 'pfw_save_changes_button_click';

	const views = [ 'pinterest_settings', 'pinterest_connection' ];

	it.each( views )(
		`${ eventName } is called on Save Settings in "%s" view.`,
		async ( view ) => {
			const { getByRole } = render(
				<SaveSettingsButton view={ view } />
			);
			const saveSettingsBtn = getByRole( 'button' );

			fireEvent.click( saveSettingsBtn );
			expect( recordEvent ).toHaveBeenCalledWith( eventName, {
				context: view,
			} );
			await waitFor( () =>
				expect( notice ).toHaveBeenCalledWith(
					'success',
					'Your settings have been saved successfully.'
				)
			);
		}
	);

	it( 'disables the button and shows loading while saving', () => {
		useSettingsSelect.mockImplementation( ( selector ) =>
			selector === 'isSettingsUpdating' ? true : {}
		);
		render( <SaveSettingsButton view="pinterest_settings" /> );
		expect(
			screen.getByRole( 'button', { name: 'Saving settings…' } )
		).toBeDisabled();
		fireEvent.click( screen.getByRole( 'button' ) );
		expect( save ).not.toHaveBeenCalled();
	} );

	it( 'reports a failed save without success and permits retry', async () => {
		save.mockRejectedValueOnce( new Error( 'Save failed' ) );
		render( <SaveSettingsButton view="pinterest_settings" /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		await waitFor( () =>
			expect( notice ).toHaveBeenCalledWith(
				'error',
				'There was a problem saving your settings.'
			)
		);
		expect( notice ).toHaveBeenCalledTimes( 1 );
		expect( resetSettings ).not.toHaveBeenCalled();
		expect( resetInteractions ).not.toHaveBeenCalled();
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		await waitFor( () =>
			expect( notice ).toHaveBeenLastCalledWith(
				'success',
				'Your settings have been saved successfully.'
			)
		);
		expect( save ).toHaveBeenCalledTimes( 2 );
		expect( resetSettings ).toHaveBeenCalledTimes( 1 );
		expect( resetInteractions ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'announces a saved advertiser connection only after success', async () => {
		useSettingsSelect.mockImplementation( ( selector ) =>
			selector === 'isSettingsUpdating'
				? false
				: { tracking_advertiser: 'advertiser', tracking_tag: 'tag' }
		);
		connectAdvertiser.mockResolvedValueOnce( true );
		render( <SaveSettingsButton view="pinterest_connection" /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		await waitFor( () =>
			expect( notice ).toHaveBeenCalledWith(
				'success',
				'The advertiser was connected successfully.'
			)
		);
		expect( connectAdvertiser ).toHaveBeenCalledWith( 'advertiser', 'tag' );
		expect( notice ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'does not announce success when advertiser connection fails', async () => {
		const settings = {
			tracking_advertiser: 'advertiser',
			tracking_tag: 'tag',
		};
		useSettingsSelect.mockImplementation( ( selector ) =>
			selector === 'isSettingsUpdating' ? false : settings
		);
		connectAdvertiser.mockRejectedValueOnce(
			new Error( 'Connection failed' )
		);
		render( <SaveSettingsButton view="pinterest_connection" /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);
		await waitFor( () =>
			expect( notice ).toHaveBeenCalledWith(
				'error',
				'There was a problem saving your settings.'
			)
		);
		expect( connectAdvertiser ).toHaveBeenCalledWith( 'advertiser', 'tag' );
		expect( notice ).toHaveBeenCalledTimes( 1 );
	} );
} );
