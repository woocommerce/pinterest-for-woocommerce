/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * Internal dependencies
 */
import SyncStateTable from './SyncStateTable';

const workflow = ( extraInfo ) => [
	{
		label: 'Remote sync status',
		status: 'error',
		status_label: 'Unknown processing result status.',
		extra_info: extraInfo,
	},
];

describe( 'SyncStateTable diagnostics', () => {
	/* eslint-disable jest/no-standalone-expect -- Check the known React warning after each independent test. */
	afterEach( () => {
		// React reports this existing warning in whichever test runs first.
		// eslint-disable-next-line no-console -- Validate only the known warning.
		if ( console.error.mock.calls.length ) {
			expect( console ).toHaveErrored();
			// eslint-disable-next-line no-console -- Validate only the known warning.
			expect( console.error ).toHaveBeenCalledTimes( 1 );
			// eslint-disable-next-line no-console -- Validate only the known warning.
			expect( console.error ).toHaveBeenCalledWith(
				expect.stringContaining(
					'The prop `caption` is marked as required'
				)
			);
		}
	} );
	/* eslint-enable jest/no-standalone-expect */

	test( 'keeps escaped remote status as literal text', () => {
		const { getByText, container } = render(
			<SyncStateTable
				workflow={ workflow(
					'&lt;em&gt;unrecognized&lt;/em&gt; &amp; pending'
				) }
			/>
		);
		expect(
			getByText( '<em>unrecognized</em> & pending' )
		).toBeInTheDocument();
		expect( container.querySelector( 'em' ) ).toBeNull();
	} );

	test( 'preserves server-approved formatting and links', () => {
		const { getByRole, getByText, container } = render(
			<SyncStateTable
				workflow={ workflow(
					'<strong>Diagnostic</strong><br /><em>Details</em> <code>NEW_STATE</code> <a href="https://example.test/feed.xml" target="_blank" rel="noopener">feed file</a>'
				) }
			/>
		);
		expect( getByText( /Diagnostic/ ) ).toBeInTheDocument();
		expect( container.querySelector( 'strong' ) ).toHaveTextContent(
			'Diagnostic'
		);
		expect( container.querySelector( 'br' ) ).not.toBeNull();
		expect( container.querySelector( 'em' ) ).toHaveTextContent(
			'Details'
		);
		expect( container.querySelector( 'code' ) ).toHaveTextContent(
			'NEW_STATE'
		);
		const link = getByRole( 'link', { name: 'feed file' } );
		expect( link ).toHaveAttribute(
			'href',
			'https://example.test/feed.xml'
		);
		expect( link ).toHaveAttribute( 'target', '_blank' );
		expect( link ).toHaveAttribute( 'rel', 'noopener' );
	} );

	test( 'preserves local settings links and plain text', () => {
		const { getByRole, getByText } = render(
			<SyncStateTable
				workflow={ workflow(
					'Visit <a href="/wp-admin/admin.php?page=wc-admin&amp;path=/pinterest/settings">settings</a> to enable sync.'
				) }
			/>
		);
		expect( getByText( /to enable sync/ ) ).toBeInTheDocument();
		expect( getByRole( 'link', { name: 'settings' } ) ).toHaveAttribute(
			'href',
			'/wp-admin/admin.php?page=wc-admin&path=/pinterest/settings'
		);
	} );
} );
