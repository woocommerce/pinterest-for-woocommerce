jest.mock( '../../helpers/effects' );
jest.mock( '@woocommerce/tracks' );

/**
 * External dependencies
 */
import { recordEvent } from '@woocommerce/tracks';
import { fireEvent, render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import AccountConnection from './Connection';

recordEvent.mockName( 'recordEvent' );

afterEach( () => {
	jest.clearAllMocks();
} );

describe( 'AccountConnection component', () => {
	it( 'Should call `pfw_modal_open { name: \'account-disconnection\', context}` track event once "Disconnect" button is clicked', () => {
		// Render connected component.
		const { getByRole } = render(
			<AccountConnection
				isConnected={ true }
				accountData={ { id: 123 } }
				context="foo"
			/>
		);
		// Find & click the Disconnect button.
		const disconnectButton = getByRole( 'button', { name: 'Disconnect' } );
		fireEvent.click( disconnectButton );

		// Assert fired event.
		expect( recordEvent ).toHaveBeenCalledWith( 'pfw_modal_open', {
			context: 'foo',
			name: 'account-disconnection',
		} );
	} );
} );
