/**
 * External dependencies
 */
import { beforeEach, describe, expect, test, vi } from 'vitest';
import { createRegistry, RegistryProvider } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import {
	act,
	fireEvent,
	render,
	screen,
	waitFor,
} from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { recordEvent } from '@woocommerce/tracks';

/**
 * Internal dependencies
 */
import CatalogSyncApp from './App';
import { STORE_NAME as INTERACTIONS_STORE } from './data/userInteraction/constants';
import { controls as interactionsControls } from './data/userInteraction/controls';
import interactionsReducer from './data/userInteraction/reducer';
import * as interactionsActions from './data/userInteraction/actions';
import * as interactionsSelectors from './data/userInteraction/selectors';
import * as interactionsResolvers from './data/userInteraction/resolvers';
import {
	OPTIONS_NAME,
	STORE_NAME as SETTINGS_STORE,
} from '../setup-guide/app/data/settings/constants';
import { controls as settingsControls } from '../setup-guide/app/data/settings/controls';
import settingsReducer from '../setup-guide/app/data/settings/reducer';
import * as settingsActions from '../setup-guide/app/data/settings/actions';
import * as settingsSelectors from '../setup-guide/app/data/settings/selectors';
import * as settingsResolvers from '../setup-guide/app/data/settings/resolvers';

vi.mock( '@wordpress/api-fetch', () => ( {
	default: vi.fn(),
} ) );

// Sections with their own requests; they take no part in the prompts.
vi.mock( '../setup-guide/app/components/HealthCheck', () => ( {
	default: () => null,
} ) );
vi.mock( '../components/navigation-classic', () => ( {
	default: () => null,
} ) );
vi.mock( './sections/SyncState', () => ( { default: () => null } ) );
vi.mock( './sections/SyncIssues', () => ( { default: () => null } ) );

const SETTINGS_PATH = '/pinterest/v1/settings';
const INTERACTIONS_PATH = '/pinterest/v1/user_interaction';

// Holds saved dismissals until the test answers them.
let settingsLoaded;

// A merchant in the ad credits campaign, tracking with the Tag only.
const SETTINGS = {
	ads_campaign_is_active: true,
	track_conversions: true,
	track_conversions_capi: false,
	tracking_advertiser: '549755885175',
	account_data: {
		is_billing_setup: false,
		currency_credit_info: { spendRequire: '$15', creditsGiven: '$125' },
	},
};

/**
 * Stands in for the REST API: serves the settings and keeps the dismissals
 * the way `UserInteraction::set_user_interaction()` does, so a fresh mount
 * reads back only what a request actually saved.
 *
 * @param {Object}  options
 * @param {Object}  [options.settings]     Plugin settings to serve.
 * @param {Object}  [options.dismissed]    Dismissals saved before the visit.
 * @param {Promise} [options.settingsGate] Holds the settings response.
 * @return {Object} The saved dismissals, updated by the requests.
 */
function serve( {
	settings = SETTINGS,
	dismissed = {},
	settingsGate = Promise.resolve(),
} ) {
	const saved = {
		ads_modal_dismissed: false,
		ads_notice_dismissed: false,
		capi_modal_dismissed: false,
		...dismissed,
	};

	apiFetch.mockImplementation( async ( { path, method = 'GET', data } ) => {
		if ( path === SETTINGS_PATH && method === 'GET' ) {
			await settingsGate;
			return { [ OPTIONS_NAME ]: settings };
		}
		if ( path === INTERACTIONS_PATH && method === 'GET' ) {
			await settingsLoaded;
			return { ...saved };
		}
		if ( path === INTERACTIONS_PATH && method === 'POST' ) {
			// The endpoint saves a single interaction per request.
			const [ name ] = Object.keys( data );
			saved[ name ] = true;
			return { [ name ]: true };
		}
		throw new Error( `Unexpected request: ${ method } ${ path }` );
	} );

	return saved;
}

/**
 * Opens the Catalog tab as a page load does: with empty stores.
 *
 * Settings resolve first by default; race tests can answer dismissals first.
 *
 * @param {Object}  [options]
 * @param {boolean} [options.holdDismissals=false]    Whether to leave the saved dismissals unanswered.
 * @param {boolean} [options.interactionsFirst=false] Answer dismissals before settings.
 * @return {Promise<Object>} The registry, `unmount`, and `answerDismissals` to answer a held request.
 */
async function openCatalog( {
	holdDismissals = false,
	interactionsFirst = false,
} = {} ) {
	let answerDismissals;
	settingsLoaded = new Promise( ( resolve ) => {
		answerDismissals = resolve;
	} );

	const registry = createRegistry();
	registry.register( noticesStore );
	registry.registerStore( SETTINGS_STORE, {
		reducer: settingsReducer,
		actions: settingsActions,
		selectors: settingsSelectors,
		resolvers: settingsResolvers,
		controls: settingsControls,
	} );
	registry.registerStore( INTERACTIONS_STORE, {
		reducer: interactionsReducer,
		actions: interactionsActions,
		selectors: interactionsSelectors,
		resolvers: interactionsResolvers,
		controls: interactionsControls,
	} );

	const { unmount } = render(
		<RegistryProvider value={ registry }>
			<CatalogSyncApp />
		</RegistryProvider>
	);

	if ( ! interactionsFirst ) {
		await waitFor( () =>
			expect(
				registry
					.select( SETTINGS_STORE )
					.hasFinishedResolution( 'getSettings', [] )
			).toBe( true )
		);
	}

	if ( ! holdDismissals ) {
		// Answer inside act() so the renders and effects the answer causes
		// (the prompts open from effects) are flushed before a test asserts.
		await act( async () => answerDismissals() );
		await waitFor( () =>
			expect(
				registry.select( INTERACTIONS_STORE ).areInteractionsLoaded()
			).toBe( true )
		);
	}

	return { registry, unmount, answerDismissals };
}

const requests = ( method ) =>
	apiFetch.mock.calls
		.map( ( [ request ] ) => ( { method: 'GET', ...request } ) )
		.filter( ( request ) => request.method === method );

const dialogs = () => screen.queryAllByRole( 'dialog' );
// The notice is also announced through the `@wordpress/a11y` live region.
const adCreditsNotice = () =>
	screen.queryByText(
		/^Spend \$15 to claim \$125 in Pinterest ad credits\./,
		{
			ignore: 'script, style, .a11y-speak-region',
		}
	);
const capiModal = () =>
	screen.queryByText( 'Improve your conversion tracking' );

describe( 'Catalog tab prompts', () => {
	beforeEach( () => {
		apiFetch.mockReset();
		recordEvent.mockClear();
	} );

	test( 'shows no prompt until the saved dismissals are known', async () => {
		serve( {
			dismissed: {
				ads_modal_dismissed: true,
				capi_modal_dismissed: true,
			},
		} );

		const { answerDismissals } = await openCatalog( {
			holdDismissals: true,
		} );

		// The settings alone make the merchant eligible for every prompt.
		expect( dialogs() ).toHaveLength( 0 );
		expect( adCreditsNotice() ).toBeNull();
		expect( recordEvent ).not.toHaveBeenCalled();

		answerDismissals();

		// Only the prompt that was not dismissed before appears.
		await waitFor( () => expect( adCreditsNotice() ).not.toBeNull() );
		expect( dialogs() ).toHaveLength( 0 );
		expect( recordEvent ).not.toHaveBeenCalled();
		expect( requests( 'GET' ) ).toHaveLength( 2 );
		expect( requests( 'POST' ) ).toHaveLength( 0 );
	} );

	test.each( [
		{
			state: 'an active campaign without billing details',
			campaign: true,
			redeemError: undefined,
			modal: 'You are one step away from claiming $125 of Pinterest ad credits.',
			notice: true,
		},
		{
			state: 'credits that the advertiser already redeemed',
			campaign: true,
			redeemError: 2322,
			modal: 'Advertiser already has a redeemed offer.',
			notice: true,
		},
		{
			state: 'an expired offer',
			campaign: true,
			redeemError: 2319,
			modal: 'Unable to claim Pinterest ads credits as the offer has expired.',
			notice: false,
		},
		{
			state: 'no active campaign',
			campaign: false,
			redeemError: undefined,
			modal: 'You have successfully set up your Pinterest integration.',
			notice: false,
		},
	] )(
		'matches the ad credits prompts to $state',
		async ( { campaign, redeemError, modal, notice } ) => {
			serve( {
				settings: {
					...SETTINGS,
					ads_campaign_is_active: campaign,
					account_data: {
						...SETTINGS.account_data,
						coupon_redeem_info: { error_id: redeemError },
					},
				},
				dismissed: { capi_modal_dismissed: true },
			} );

			await openCatalog();

			// openCatalog() flushes the prompt effects; waitFor() keeps the
			// check robust to a later render pass.
			await waitFor( () => {
				expect( dialogs() ).toHaveLength( 1 );
				expect( screen.queryByText( modal ) ).not.toBeNull();
				expect( adCreditsNotice() !== null ).toBe( notice );
			} );
		}
	);

	test.each( [
		{ tag: true, capi: false, shown: true },
		{ tag: true, capi: true, shown: false },
		{ tag: false, capi: false, shown: false },
	] )(
		'suggests the Conversions API with Tag $tag and CAPI $capi: $shown',
		async ( { tag, capi, shown } ) => {
			serve( {
				settings: {
					...SETTINGS,
					track_conversions: tag,
					track_conversions_capi: capi,
				},
				dismissed: { ads_modal_dismissed: true },
			} );

			await openCatalog();

			// openCatalog() flushed the prompt effects, so a missing modal
			// here is the effect's decision, not a render still pending.
			expect( dialogs() ).toHaveLength( shown ? 1 : 0 );
			expect( capiModal() !== null ).toBe( shown );
		}
	);

	test.each( [
		{
			prompt: 'onboarding modal',
			flag: 'ads_modal_dismissed',
			// Leaves the modal as the only open dialog.
			dismissed: { capi_modal_dismissed: true },
			find: () => screen.queryByText( /^You are one step away/ ),
			close: () =>
				screen.getByRole( 'button', { name: 'Do this later' } ),
			events: [
				[
					'pfw_modal_open',
					{ context: 'catalog-sync', name: 'ads-credits-onboarding' },
				],
				[
					'pfw_modal_closed',
					{ context: 'catalog-sync', name: 'ads-credits-onboarding' },
				],
			],
		},
		{
			prompt: 'ad credits notice',
			flag: 'ads_notice_dismissed',
			// An open modal hides the notice from assistive technology.
			dismissed: {
				ads_modal_dismissed: true,
				capi_modal_dismissed: true,
			},
			find: adCreditsNotice,
			close: () =>
				screen.getByRole( 'button', { name: 'Dismiss this notice' } ),
			events: [ [ 'pfw_ads_credits_success_notice' ] ],
		},
		{
			prompt: 'Conversions API modal',
			flag: 'capi_modal_dismissed',
			dismissed: { ads_modal_dismissed: true },
			find: capiModal,
			close: () => screen.getByRole( 'button', { name: 'Not now' } ),
			events: [],
		},
	] )(
		'saves the dismissal of the $prompt alone and hides it on the next visit',
		async ( { flag, dismissed, find, close, events } ) => {
			const saved = serve( { dismissed } );
			const { registry, unmount } = await openCatalog();
			await waitFor( () => expect( find() ).not.toBeNull() );

			fireEvent.click( close() );

			expect( find() ).toBeNull();
			expect( recordEvent.mock.calls ).toEqual( events );
			await waitFor( () => expect( saved[ flag ] ).toBe( true ) );
			// The endpoint handles one interaction per request, and nothing else is saved.
			expect( requests( 'POST' ) ).toEqual( [
				{
					path: INTERACTIONS_PATH,
					method: 'POST',
					data: { [ flag ]: true },
				},
			] );
			// The other prompts keep their own state.
			expect(
				registry.select( INTERACTIONS_STORE ).getUserInteractions()
			).toEqual( {
				ads_modal_dismissed: false,
				ads_notice_dismissed: false,
				capi_modal_dismissed: false,
				...dismissed,
				[ flag ]: true,
			} );

			unmount();
			apiFetch.mockClear();
			await openCatalog();

			expect( find() ).toBeNull();
			expect( requests( 'GET' ) ).toHaveLength( 2 );
			expect( requests( 'POST' ) ).toHaveLength( 0 );
		}
	);

	test( 'asks about the Conversions API again after the modal was only closed', async () => {
		serve( { dismissed: { ads_modal_dismissed: true } } );
		const { unmount } = await openCatalog();

		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Close dialog' } )
		);

		expect( capiModal() ).toBeNull();
		expect( requests( 'POST' ) ).toHaveLength( 0 );

		unmount();
		await openCatalog();

		await waitFor( () => expect( capiModal() ).not.toBeNull() );
	} );
	test( 'shows onboarding before CAPI and never saves an automatic dismissal', async () => {
		const saved = serve( {} );
		await openCatalog();
		await waitFor( () => {
			expect( dialogs() ).toHaveLength( 1 );
			expect(
				screen.queryByText( /^You are one step away/ )
			).not.toBeNull();
		} );
		expect( capiModal() ).toBeNull();
		expect( saved.ads_modal_dismissed ).toBe( false );
		expect( requests( 'POST' ) ).toHaveLength( 0 );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Do this later' } )
		);
		await waitFor( () => expect( capiModal() ).not.toBeNull() );
		expect( dialogs() ).toHaveLength( 1 );
		expect( saved.ads_modal_dismissed ).toBe( true );
	} );

	test( 'closing CAPI keeps it closed after dismissing the ad credits notice', async () => {
		serve( { dismissed: { ads_modal_dismissed: true } } );
		const { registry } = await openCatalog();
		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Close dialog' } )
		);
		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Dismiss this notice' } )
		);
		await waitFor( () =>
			expect(
				registry.select( INTERACTIONS_STORE ).getUserInteractions()
					.ads_notice_dismissed
			).toBe( true )
		);
		expect( capiModal() ).toBeNull();
		expect( requests( 'POST' ).map( ( { data } ) => data ) ).toEqual( [
			{ ads_notice_dismissed: true },
		] );
	} );

	test.each( [ undefined, false, 2322, 2319 ] )(
		'late settings with coupon %s decide prompts once',
		async ( errorId ) => {
			let answerSettings;
			const settingsGate = new Promise( ( resolve ) => {
				answerSettings = resolve;
			} );
			serve( {
				settingsGate,
				settings: {
					...SETTINGS,
					account_data: {
						...SETTINGS.account_data,
						coupon_redeem_info: { error_id: errorId },
					},
				},
				dismissed: { capi_modal_dismissed: true },
			} );
			const { registry } = await openCatalog( {
				interactionsFirst: true,
			} );
			expect(
				recordEvent.mock.calls.filter(
					( [ event ] ) => event === 'pfw_modal_open'
				)
			).toHaveLength( 0 );
			await act( async () => answerSettings() );
			await waitFor( () =>
				expect(
					registry
						.select( SETTINGS_STORE )
						.hasFinishedResolution( 'getSettings', [] )
				).toBe( true )
			);
			await waitFor( () => {
				expect(
					recordEvent.mock.calls.filter(
						( [ event ] ) => event === 'pfw_modal_open'
					)
				).toHaveLength( 1 );
				expect( adCreditsNotice() !== null ).toBe(
					! errorId || errorId === 2322
				);
			} );
		}
	);
} );
