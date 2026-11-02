/**
 * External dependencies
 */
import { describe, expect, it, vi } from 'vitest';
import '@testing-library/jest-dom/vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import SetupPins from './SetupPins';
import { useSettingsSelect, useSettingsDispatch } from '../helpers/effects';
import { expectKnownReactDeprecations } from '../../../tests/known-react-deprecations';

vi.mock( '../helpers/effects', () => ( {
	useSettingsSelect: vi.fn(),
	useSettingsDispatch: vi.fn(),
	useCreateNotice: () => vi.fn(),
} ) );

describe( 'Tag settings', () => {
	expectKnownReactDeprecations( 'Card' );

	it( 'disables Enhanced Match without a Tag but keeps CAPI available', async () => {
		const update = vi.fn();
		const settings = {
			track_conversions: true,
			track_conversions_capi: false,
			tracking_tag: '',
			enhanced_match_support: true,
		};
		useSettingsDispatch.mockReturnValue( update );
		useSettingsSelect.mockReturnValue( settings );
		const { rerender } = render( <SetupPins /> );
		const enhancedMatch = () =>
			screen.getByRole( 'checkbox', { name: 'Enhanced Match support' } );
		expect( enhancedMatch() ).toBeDisabled();
		expect(
			screen.getByText( /Select a Tag in the Connection tab/ )
		).toBeVisible();
		await userEvent
			.setup()
			.click(
				screen.getByRole( 'checkbox', { name: 'Conversions API' } )
			);
		expect( update ).toHaveBeenCalledWith( {
			track_conversions_capi: true,
		} );

		useSettingsSelect.mockReturnValue( {
			...settings,
			tracking_tag: '123',
		} );
		rerender( <SetupPins /> );
		expect( enhancedMatch() ).toBeEnabled();
		expect(
			screen.queryByText( /Select a Tag in the Connection tab/ )
		).toBeNull();
	} );
} );
