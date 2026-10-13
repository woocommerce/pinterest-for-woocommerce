/**
 * External dependencies
 */
import { beforeEach, describe, expect, it, vi } from 'vitest';

const firstPage = {
	lines: [
		{
			status: 'error',
			product_name: 'Hoodie',
			product_edit_link: 'https://example.com/?post=12&action=edit',
			issue_description: 'Image is too small.',
		},
		{
			status: 'warning',
			product_name: 'Beanie',
			product_edit_link: 'https://example.com/?post=15&action=edit',
			issue_description: 'Description is missing.',
		},
	],
	total_rows: 12,
};

const refreshedPage = {
	lines: [
		{
			status: 'warning',
			product_name: 'Beanie',
			product_edit_link: 'https://example.com/?post=15&action=edit',
			issue_description: 'Description is missing.',
		},
	],
	total_rows: 1,
};

const feedState = {
	workflow: [
		{
			label: 'XML feed',
			status: 'success',
			status_label: 'Up to date',
			extra_info: 'Wrote 12 products.',
		},
	],
	overview: { total: 12, not_synced: 2, warnings: 1, errors: 1 },
};

const refreshedFeedState = {
	workflow: [
		{
			label: 'XML feed',
			status: 'pending',
			status_label: 'Feed generation in progress.',
			extra_info: '',
		},
	],
	overview: { total: 0, not_synced: 0, warnings: 0, errors: 0 },
};

describe( 'reports store', () => {
	let apiFetch;
	let reports;
	let resolveReports;
	let dispatchReports;

	// The store registers itself in the default registry on import, so load a fresh copy
	// and a fresh transport per test.
	beforeEach( async () => {
		vi.resetModules();
		vi.doMock( '@wordpress/api-fetch', () => ( { default: vi.fn() } ) );
		apiFetch = ( await import( '@wordpress/api-fetch' ) ).default;
		const data = await import( '@wordpress/data' );
		const { REPORTS_STORE_NAME } = await import( './index' );
		reports = () => data.select( REPORTS_STORE_NAME );
		resolveReports = () => data.resolveSelect( REPORTS_STORE_NAME );
		dispatchReports = () => data.dispatch( REPORTS_STORE_NAME );
	} );

	it( 'requests the selected page, reports loading until it arrives and stores the rows', async () => {
		let respond;
		apiFetch.mockReturnValue(
			new Promise( ( resolve ) => {
				respond = resolve;
			} )
		);
		const query = { paged: 3, per_page: 10 };

		expect( reports().isRequesting() ).toBe( false );
		expect( reports().getFeedIssues( query ) ).toEqual( {} );
		await vi.waitFor( () => expect( apiFetch ).toHaveBeenCalled() );

		expect( apiFetch.mock.calls ).toEqual( [
			[ { path: '/pinterest/v1/feed_issues?paged=3&per_page=10' } ],
		] );
		expect( reports().isRequesting() ).toBe( true );
		expect( reports().getFeedIssues( query ) ).toEqual( {} );

		respond( firstPage );

		expect( await resolveReports().getFeedIssues( query ) ).toEqual(
			firstPage
		);
		expect( reports().isRequesting() ).toBe( false );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );

	it.each( [
		[ 'no pagination', {}, 'paged=1&per_page=25' ],
		[ 'only a page', { paged: 2 }, 'paged=2&per_page=25' ],
		[ 'only a page size', { per_page: 50 }, 'paged=1&per_page=50' ],
	] )(
		'defaults to the first page and 25 rows for a query with %s',
		async ( name, query, expected ) => {
			apiFetch.mockResolvedValue( firstPage );

			await resolveReports().getFeedIssues( query );

			expect( apiFetch.mock.calls ).toEqual( [
				[ { path: `/pinterest/v1/feed_issues?${ expected }` } ],
			] );
		}
	);

	it( 'drops the loaded reports and requests them again after the feed is reset', async () => {
		const query = { paged: 1, per_page: 25 };
		const respondTo = ( responses ) =>
			apiFetch.mockImplementation( ( { path } ) =>
				Promise.resolve( responses[ path ] )
			);
		respondTo( {
			'/pinterest/v1/feed_issues?paged=1&per_page=25': firstPage,
			'/pinterest/v1/feed_state': feedState,
		} );
		await resolveReports().getFeedIssues( query );
		await resolveReports().getFeedState();

		// What saving the settings does to refresh the catalog page.
		dispatchReports().resetFeed();
		dispatchReports().invalidateResolutionForStore();

		respondTo( {
			'/pinterest/v1/feed_issues?paged=1&per_page=25': refreshedPage,
			'/pinterest/v1/feed_state': refreshedFeedState,
		} );
		expect( reports().getFeedIssues( query ) ).toEqual( {} );
		expect( reports().getFeedState() ).toEqual( {} );
		expect( await resolveReports().getFeedIssues( query ) ).toEqual(
			refreshedPage
		);
		expect( await resolveReports().getFeedState() ).toEqual(
			refreshedFeedState
		);
		expect( reports().isRequesting() ).toBe( false );
		expect( apiFetch ).toHaveBeenCalledTimes( 4 );
	} );

	it( 'requests the feed state and stores it without the issues loading flag', async () => {
		let respond;
		apiFetch.mockReturnValue(
			new Promise( ( resolve ) => {
				respond = resolve;
			} )
		);

		expect( reports().getFeedState() ).toEqual( {} );
		await vi.waitFor( () => expect( apiFetch ).toHaveBeenCalled() );

		expect( apiFetch.mock.calls ).toEqual( [
			[ { path: '/pinterest/v1/feed_state' } ],
		] );
		expect( reports().isRequesting() ).toBe( false );

		respond( feedState );

		expect( await resolveReports().getFeedState() ).toEqual( feedState );
		expect( reports().isRequesting() ).toBe( false );
	} );
} );
