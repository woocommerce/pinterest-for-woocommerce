/**
 * External dependencies
 */
import { createPortal } from '@wordpress/element';
import { recordEvent } from '@woocommerce/tracks';
import { fireEvent, render, waitFor } from '@testing-library/react';

/**
 * Internal dependencies
 */
import documentationLinkProps, {
	useDocumentationLinkTracking,
} from './documentation-link-props';

const payload = { href: '#documentation', linkId: 'help', context: 'settings' };
const Owner = ( { children } ) => {
	useDocumentationLinkTracking();
	return <div>{ children }</div>;
};

afterEach( () => jest.clearAllMocks() );

it( 'tracks a nested target without replacing a later onClick prop', async () => {
	const onClick = jest.fn( ( event ) => event.stopPropagation() );
	const { getByText } = render(
		<Owner>
			<a
				href={ payload.href }
				{ ...documentationLinkProps( payload ) }
				onClick={ onClick }
			>
				<span>Documentation</span>
			</a>
		</Owner>
	);
	fireEvent.click( getByText( 'Documentation' ) );
	expect( onClick ).toHaveBeenCalledTimes( 1 );
	await waitFor( () =>
		expect( recordEvent ).toHaveBeenCalledWith(
			'pfw_documentation_link_click',
			{ link_id: 'help', context: 'settings', href: '#documentation' }
		)
	);
} );

it( 'preserves an earlier onClick prop and a handler supplied to the helper', async () => {
	const earlier = jest.fn();
	const supplied = jest.fn();
	expect( documentationLinkProps( payload ) ).not.toHaveProperty( 'onClick' );
	expect(
		documentationLinkProps( { ...payload, onClick: supplied } ).onClick
	).toBe( supplied );
	const { getByText } = render(
		<Owner>
			<a
				href={ payload.href }
				onClick={ earlier }
				{ ...documentationLinkProps( payload ) }
			>
				Earlier
			</a>
			<a
				{ ...documentationLinkProps( {
					...payload,
					onClick: supplied,
					eventName: 'pfw_get_started_notice_link_click',
				} ) }
			>
				Supplied
			</a>
		</Owner>
	);
	fireEvent.click( getByText( 'Earlier' ) );
	fireEvent.click( getByText( 'Supplied' ) );
	await waitFor( () => expect( recordEvent ).toHaveBeenCalledTimes( 2 ) );
	expect( earlier ).toHaveBeenCalledTimes( 1 );
	expect( supplied ).toHaveBeenCalledTimes( 1 );
	expect( recordEvent ).toHaveBeenLastCalledWith(
		'pfw_get_started_notice_link_click',
		{ link_id: 'help', context: 'settings', href: '#documentation' }
	);
} );

it( 'honors prevented navigation and ignores unmarked links', async () => {
	const { getByText } = render(
		<Owner>
			<a
				href={ payload.href }
				{ ...documentationLinkProps( payload ) }
				onClick={ ( event ) => event.preventDefault() }
			>
				Prevented
			</a>
			<a href="#unmarked">Unmarked</a>
		</Owner>
	);
	expect( fireEvent.click( getByText( 'Prevented' ) ) ).toBe( false );
	expect( fireEvent.click( getByText( 'Unmarked' ) ) ).toBe( true );
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	expect( recordEvent ).not.toHaveBeenCalled();
} );

it( 'shares the listener across owners and removes it after the last owner unmounts', async () => {
	const add = jest.spyOn( document, 'addEventListener' );
	const remove = jest.spyOn( document, 'removeEventListener' );
	const first = render( <Owner /> );
	const second = render(
		<Owner>
			<a { ...documentationLinkProps( payload ) }>Shared</a>
		</Owner>
	);
	const clickRegistrations = () =>
		add.mock.calls.filter(
			( [ type, , capture ] ) => type === 'click' && capture === true
		);
	expect( clickRegistrations() ).toHaveLength( 1 );
	const listener = clickRegistrations()[ 0 ][ 1 ];
	first.unmount();
	expect( remove ).not.toHaveBeenCalledWith( 'click', listener, true );
	fireEvent.click( second.getByText( 'Shared' ) );
	await waitFor( () => expect( recordEvent ).toHaveBeenCalledTimes( 1 ) );
	second.rerender(
		<Owner>
			<a
				{ ...documentationLinkProps( {
					...payload,
					linkId: 'changed',
					context: 'rerender',
					href: '#changed',
				} ) }
			>
				Updated
			</a>
		</Owner>
	);
	fireEvent.click( second.getByText( 'Updated' ) );
	await waitFor( () =>
		expect( recordEvent ).toHaveBeenLastCalledWith(
			'pfw_documentation_link_click',
			{ link_id: 'changed', context: 'rerender', href: '#changed' }
		)
	);
	expect( clickRegistrations() ).toHaveLength( 1 );
	second.unmount();
	expect( remove ).toHaveBeenCalledWith( 'click', listener, true );
	const again = render(
		<Owner>
			<a { ...documentationLinkProps( payload ) }>Again</a>
		</Owner>
	);
	fireEvent.click( again.getByText( 'Again' ) );
	await waitFor( () => expect( recordEvent ).toHaveBeenCalledTimes( 3 ) );
	again.unmount();
	expect( clickRegistrations() ).toHaveLength( 2 );
	add.mockRestore();
	remove.mockRestore();
} );

it( 'tracks documentation links inside modal portals', async () => {
	render(
		<Owner>
			{ createPortal(
				<a { ...documentationLinkProps( payload ) }>Modal help</a>,
				document.body
			) }
		</Owner>
	);
	fireEvent.click(
		document.querySelector( '[data-pfw-doc-link-id="help"]' )
	);
	await waitFor( () => expect( recordEvent ).toHaveBeenCalledTimes( 1 ) );
} );
