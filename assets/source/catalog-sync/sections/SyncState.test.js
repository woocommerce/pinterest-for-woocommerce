/**
 * External dependencies
 */
import { afterEach, describe, expect, test, vi } from 'vitest';
import { render, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom/vitest';
import { recordEvent } from '@woocommerce/tracks';

vi.mock( '../../setup-guide/app/helpers/effects', () => ( {
	useSettingsSelect: vi.fn(),
} ) );
// The reports store requests the feed state on render. Leave it pending, as
// the request cannot complete here and its rejection is not handled.
vi.mock( '@wordpress/api-fetch', () => ( {
	default: vi.fn( () => new Promise( () => {} ) ),
} ) );

/**
 * Internal dependencies
 */
import SyncState from './SyncState';
import { useSettingsSelect } from '../../setup-guide/app/helpers/effects';
import { expectKnownReactDeprecations } from '../../tests/known-react-deprecations';

describe( 'SyncState component', () => {
	expectKnownReactDeprecations( 'Card' );

	afterEach( () => useSettingsSelect.mockReset() );

	test.each( [
		[
			'<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">&euro;</span>1,234.50</bdi></span>',
			'€1,234.50',
		],
		[ '<em>Account credit</em> &amp; pending', 'Account credit & pending' ],
		[ '&lt;em&gt;Account credit&lt;/em&gt;', '<em>Account credit</em>' ],
		[ '0.00', '0.00' ],
	] )( 'renders credit value as text: %s', ( credit, expected ) => {
		useSettingsSelect.mockReturnValue( {
			account_data: {
				available_discounts: {
					marketing_offer: { remaining_discount: credit },
				},
			},
		} );
		const { container } = render( <SyncState /> );
		const notice = container.querySelector(
			'.pinterest-for-woocommerce-catalog-sync__state-footer-credits'
		);
		expect( notice ).toHaveTextContent(
			`You have ${ expected } of free ad credits left to use`
		);
		expect(
			notice.querySelector( 'em, .woocommerce-Price-amount' )
		).toBeNull();
	} );
	test( 'should render header and footer correctly', () => {
		const { getByRole } = render( <SyncState /> );

		expect( getByRole( 'heading' ) ).toHaveTextContent( 'Overview' );
		expect( getByRole( 'link' ) ).toHaveTextContent(
			'Pinterest ads manager(opens in a new tab)'
		);
	} );

	test( 'should render SyncStateSummary', () => {
		const { getAllByTestId } = render( <SyncState /> );

		expect( getAllByTestId( 'summary-placeholder' ) ).toBeTruthy();
	} );

	test( 'should render SyncStateTable', () => {
		const { getByText } = render( <SyncState /> );

		expect( getByText( 'Property' ) ).toBeTruthy();
	} );

	test( 'should fire `pfw_ads_manager_link_click` when "Pinterest ads manager" is clicked', () => {
		const { getByText } = render( <SyncState /> );

		fireEvent.click( getByText( 'Pinterest ads manager' ) );

		expect( recordEvent ).toHaveBeenCalledWith(
			'pfw_ads_manager_link_click'
		);
	} );
} );
