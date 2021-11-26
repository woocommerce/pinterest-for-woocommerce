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
		const { getByText, getByRole } = render( <SyncState /> );

		expect( getByRole( 'heading' ) ).toHaveTextContent( 'Overview' );

		expect(
			getByText( 'Set up and manage ads to increase your reach with' )
		).toBeTruthy();

		expect( getByRole( 'link' ) ).toHaveTextContent(
			'Pinterest ads manager'
		);
	} );
} );
