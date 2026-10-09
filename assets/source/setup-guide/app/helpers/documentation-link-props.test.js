/**
 * External dependencies
 */
import { expect, it, vi } from 'vitest';
import { recordEvent } from '@woocommerce/tracks';
import { fireEvent, render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import documentationLinkProps from './documentation-link-props';

it( 'tracks the click without taking over the `onClick` prop', () => {
	const onClick = vi.fn( ( event ) => event.stopPropagation() );
	const { getByText } = render(
		<>
			<a
				href="#documentation"
				{ ...documentationLinkProps( {
					href: '#documentation',
					linkId: 'help',
					context: 'settings',
				} ) }
				onClick={ onClick }
			>
				<span>Documentation</span>
			</a>
			<a href="#unmarked">Unmarked</a>
		</>
	);

	fireEvent.click( getByText( 'Unmarked' ) );
	expect( recordEvent ).not.toHaveBeenCalled();

	fireEvent.click( getByText( 'Documentation' ) );
	expect( onClick ).toHaveBeenCalledTimes( 1 );
	expect( recordEvent ).toHaveBeenCalledTimes( 1 );
	expect( recordEvent ).toHaveBeenCalledWith(
		'pfw_documentation_link_click',
		{ link_id: 'help', context: 'settings', href: '#documentation' }
	);
} );
