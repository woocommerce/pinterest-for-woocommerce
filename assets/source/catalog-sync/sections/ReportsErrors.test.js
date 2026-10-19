/** External dependencies */
import { describe, expect, test, vi } from 'vitest';
import { createRegistry, RegistryProvider } from '@wordpress/data';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';

/** Internal dependencies */
import SyncState from './SyncState';
import SyncIssues from './SyncIssues';
import reducer from '../data/reports/reducer';
import * as actions from '../data/reports/actions';
import * as selectors from '../data/reports/selectors';
import * as resolvers from '../data/reports/resolvers';
import { controls } from '../data/reports/controls';
import { STORE_NAME } from '../data/reports/constants';
import { expectKnownReactDeprecations } from '../../tests/known-react-deprecations';

vi.mock( '@wordpress/api-fetch', () => ( { default: vi.fn() } ) );
// Child report rendering has separate tests; keep this test on failure and retry.
vi.mock( './SyncStateSummary', () => ( {
	default: ( { overview } ) =>
		overview ? (
			<span>Overview loaded</span>
		) : (
			<span data-testid="summary-placeholder" />
		),
} ) );
vi.mock( './SyncStateTable', () => ( {
	default: ( { workflow } ) =>
		workflow
			? workflow.map( ( { label } ) => (
					<span key={ label }>{ label }</span>
				) )
			: null,
} ) );
vi.mock( '../../setup-guide/app/helpers/effects', () => ( {
	useSettingsSelect: () => ( {} ),
} ) );
vi.mock( '@woocommerce/navigation', () => ( {
	getQuery: () => ( {} ),
	getHistory: () => ( { listen: () => {} } ),
	onQueryChange: () => {},
} ) );

describe( 'catalog report failures', () => {
	expectKnownReactDeprecations( 'Card' );
	test( 'shows separate errors and retries each failed report without hiding the other error', async () => {
		const registry = createRegistry();
		registry.registerStore( STORE_NAME, {
			reducer,
			actions,
			selectors,
			resolvers,
			controls,
		} );
		apiFetch.mockRejectedValue( { message: 'Offline' } );
		const textOptions = { ignore: 'script, style, .a11y-speak-region' };
		render(
			<RegistryProvider value={ registry }>
				<SyncState />
				<SyncIssues />
			</RegistryProvider>
		);
		await screen.findByText(
			'Unable to load the catalog overview.',
			textOptions
		);
		await screen.findByText(
			'Unable to load catalog issues.',
			textOptions
		);
		expect( registry.select( STORE_NAME ).isRequesting() ).toBe( false );
		expect( screen.queryByTestId( 'summary-placeholder' ) ).toBeNull();

		apiFetch.mockImplementation( ( { path } ) =>
			Promise.resolve(
				path.includes( 'feed_state' )
					? {
							overview: {
								total: 1,
								not_synced: 0,
								warnings: 0,
								errors: 0,
							},
							workflow: [
								{
									label: 'Recovered feed',
									status: 'success',
									status_label: 'Up to date',
								},
							],
						}
					: { lines: [], total_rows: 0 }
			)
		);
		fireEvent.click(
			screen.getAllByRole( 'button', { name: 'Try again' } )[ 0 ]
		);
		await screen.findByText( 'Recovered feed' );
		expect(
			screen.queryByText( 'Unable to load catalog issues.', textOptions )
		).not.toBeNull();
		fireEvent.click( screen.getByRole( 'button', { name: 'Try again' } ) );
		await waitFor( () =>
			expect(
				screen.queryByText(
					'Unable to load catalog issues.',
					textOptions
				)
			).toBeNull()
		);
		expect( apiFetch ).toHaveBeenCalledTimes( 4 );
	} );
} );
