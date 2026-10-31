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

	it( 'keeps CAPI available while Tag-only matching is inactive without a Tag', async () => {
		const update = vi.fn();
		useSettingsDispatch.mockReturnValue( update );
		useSettingsSelect.mockReturnValue( {
			track_conversions: true,
			track_conversions_capi: false,
			tracking_tag: '',
			enhanced_match_support: true,
			automatic_enhanced_match_support: true,
		} );
		render( <SetupPins /> );
		expect(
			screen.getByRole( 'checkbox', { name: 'Track conversions' } )
		).toBeEnabled();
		expect(
			screen.getByRole( 'checkbox', { name: 'Enhanced Match support' } )
		).toBeDisabled();
		expect(
			screen.getByRole( 'checkbox', { name: 'Enhanced Match support' } )
		).toBeChecked();
		expect(
			screen.getByRole( 'checkbox', {
				name: 'Automatic Enhanced Match support',
			} )
		).toBeChecked();
		expect(
			screen.getByText( /Select a Tag in the Connection tab/ )
		).toBeVisible();
		expect(
			screen.getByText(
				/Your saved Enhanced Match preference takes effect/
			)
		).toBeVisible();
		await userEvent
			.setup()
			.click(
				screen.getByRole( 'checkbox', { name: 'Conversions API' } )
			);
		expect( update ).toHaveBeenCalledWith( {
			track_conversions_capi: true,
		} );
	} );

	it( 'enables Enhanced Match for a selected Tag with tracking on', async () => {
		const update = vi.fn();
		useSettingsDispatch.mockReturnValue( update );
		useSettingsSelect.mockReturnValue( {
			track_conversions: true,
			tracking_tag: '123',
			enhanced_match_support: false,
		} );
		render( <SetupPins /> );
		await userEvent.setup().click(
			screen.getByRole( 'checkbox', {
				name: 'Enhanced Match support',
			} )
		);
		expect( update ).toHaveBeenCalledWith( {
			enhanced_match_support: true,
		} );
		expect(
			screen.queryByText( /Select a Tag in the Connection tab/ )
		).toBeNull();
	} );
} );
