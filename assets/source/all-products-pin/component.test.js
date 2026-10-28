/**
 * External dependencies
 */
import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ProductPin from './component';

describe( 'All Products Save link', () => {
	it( 'carries the complete current product and image through pagination', () => {
		const product = {
			id: 42,
			name: 'Salt &amp; Pepper &quot;Duo&quot; = 50% #1',
			permalink: 'https://example.com/?post_type=product&p=42',
			images: [ { src: 'https://example.com/salt%20%26%20pepper.jpg' } ],
		};
		const { rerender } = render( <ProductPin product={ product } /> );
		const link = screen.getByRole( 'link', {
			name: 'Save to Pinterest: Salt & Pepper "Duo" = 50% #1 (opens in a new window)',
		} );
		const url = new URL( link.href );
		expect( url.searchParams.get( 'url' ) ).toBe( product.permalink );
		expect( url.searchParams.get( 'media' ) ).toBe(
			product.images[ 0 ].src
		);
		expect( url.searchParams.get( 'description' ) ).toBe(
			'Salt & Pepper "Duo" = 50% #1'
		);
		expect( link.getAttribute( 'data-pin-do' ) ).toBe( 'none' );
		expect( link.target ).toBe( '_blank' );
		expect( link.rel ).toContain( 'noopener' );

		rerender(
			<ProductPin
				product={ {
					...product,
					id: 43,
					name: 'Next product',
					permalink: 'https://example.com/next/',
				} }
			/>
		);
		const next = screen.getByRole( 'link', {
			name: 'Save to Pinterest: Next product (opens in a new window)',
		} );
		expect( new URL( next.href ).searchParams.get( 'url' ) ).toBe(
			'https://example.com/next/'
		);
	} );

	it( 'defers to the Pinterest browser extension', () => {
		document.documentElement.setAttribute( 'data-pinterest-extension', '' );
		try {
			render(
				<ProductPin
					product={ {
						id: 42,
						name: 'Mug',
						permalink: 'https://example.com/mug/',
						images: [ { src: 'https://example.com/mug.jpg' } ],
					} }
				/>
			);
			expect( screen.queryByRole( 'link' ) ).toBeNull();
		} finally {
			document.documentElement.removeAttribute(
				'data-pinterest-extension'
			);
		}
	} );

	it( 'does not offer another product image for loading or imageless cards', () => {
		const { rerender } = render( <ProductPin /> );
		expect( screen.queryByRole( 'link' ) ).toBeNull();
		rerender(
			<ProductPin
				product={ {
					id: 42,
					name: 'No image',
					permalink: 'https://example.com/product/',
				} }
			/>
		);
		expect( screen.queryByRole( 'link' ) ).toBeNull();
	} );
} );
