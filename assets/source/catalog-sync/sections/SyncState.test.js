/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * Internal dependencies
 */
import SyncState from './SyncState';

describe( 'SyncState component', () => {
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
} );
