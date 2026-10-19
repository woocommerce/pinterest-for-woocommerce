/**
 * External dependencies
 */
import '@wordpress/notices';
import { useSelect } from '@wordpress/data';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { recordEvent } from '@woocommerce/tracks';

/**
 * Internal dependencies
 */
import SyncState from './sections/SyncState';
import AdCreditsNotice from './sections/AdCreditsNotice';
import SyncIssues from './sections/SyncIssues';
import HealthCheck from '../setup-guide/app/components/HealthCheck';
import CapiEnablementModal from '../setup-guide/app/components/CapiEnablementModal';
import {
	useCreateNotice,
	useDismissAdsModalDispatch,
	useDismissCapiModalDispatch,
} from './helpers/effects';
import NavigationClassic from '../components/navigation-classic';
import OnboardingModals from './components/OnboardingModals';
import { USER_INTERACTION_STORE_NAME } from './data';
import { useSettingsSelect } from '../setup-guide/app/helpers/effects';
import { SETTINGS_STORE_NAME } from '../setup-guide/app/data';

/**
 * Opening a modal.
 *
 * @event wcadmin_pfw_modal_open
 * @property {string} name    Ads Onboarding Modal.
 * @property {string} context catalog-sync
 */
/**
 * Closing a modal.
 *
 * @event wcadmin_pfw_modal_closed
 * @property {string} name    Ads Onboarding Modal.
 * @property {string} context catalog-sync
 */

/**
 * Catalog Sync Tab.
 *
 * @fires wcadmin_pfw_modal_open with `{ name: 'ads-credits-onboarding' }`
 * @fires wcadmin_pfw_modal_close with `{ name: 'ads-credits-onboarding' }`
 *
 * @return {JSX.Element} rendered component
 */
const CatalogSyncApp = () => {
	const appSettings = useSettingsSelect();
	const adsCampaignIsActive = appSettings?.ads_campaign_is_active;

	const couponRedeemErrorID =
		appSettings?.account_data?.coupon_redeem_info?.error_id;

	useCreateNotice( wcSettings.pinterest_for_woocommerce.error );
	const [ isOnboardingModalOpen, setIsOnboardingModalOpen ] =
		useState( false );
	const [ isCapiModalOpen, setIsCapiModalOpen ] = useState( false );
	const hasClosedCapiModal = useRef( false );

	const userInteractions = useSelect( ( select ) =>
		select( USER_INTERACTION_STORE_NAME ).getUserInteractions()
	);

	const userInteractionsLoaded = useSelect( ( select ) =>
		select( USER_INTERACTION_STORE_NAME ).areInteractionsLoaded()
	);

	const settingsLoaded = useSelect( ( select ) =>
		select( SETTINGS_STORE_NAME ).hasFinishedResolution( 'getSettings', [] )
	);
	const isAdCreditsNoticeOpen =
		settingsLoaded &&
		userInteractionsLoaded &&
		! userInteractions?.ads_notice_dismissed &&
		// Successful redemptions store a false error ID.
		( ! couponRedeemErrorID || couponRedeemErrorID === 2322 );

	const openOnboardingModal = useCallback( () => {
		if (
			! settingsLoaded ||
			userInteractionsLoaded === false ||
			userInteractions?.ads_modal_dismissed
		) {
			return;
		}

		setIsOnboardingModalOpen( true );
		recordEvent( 'pfw_modal_open', {
			context: 'catalog-sync',
			name: 'ads-credits-onboarding',
		} );
	}, [
		settingsLoaded,
		userInteractions?.ads_modal_dismissed,
		userInteractionsLoaded,
	] );

	const openCapiModal = useCallback( () => {
		if (
			! settingsLoaded ||
			userInteractionsLoaded === false ||
			userInteractions?.capi_modal_dismissed ||
			! userInteractions?.ads_modal_dismissed ||
			hasClosedCapiModal.current
		) {
			return;
		}

		const trackConversions = appSettings?.track_conversions;
		const trackConversionsCapi = appSettings?.track_conversions_capi;

		// Only show if track_conversions is enabled but CAPI is not enabled
		if ( ! trackConversions || trackConversionsCapi ) {
			return;
		}

		setIsCapiModalOpen( true );
	}, [
		settingsLoaded,
		userInteractionsLoaded,
		userInteractions?.capi_modal_dismissed,
		userInteractions?.ads_modal_dismissed,
		appSettings?.track_conversions,
		appSettings?.track_conversions_capi,
	] );

	const closeOnboardingModal = () => {
		setIsOnboardingModalOpen( false );
		handleSetDismissAdsModal();
		recordEvent( 'pfw_modal_closed', {
			context: 'catalog-sync',
			name: 'ads-credits-onboarding',
		} );
	};

	const setDismissCapiModal = useDismissCapiModalDispatch();
	const handleSetDismissCapiModal = useCallback( async () => {
		try {
			await setDismissCapiModal();
		} catch ( error ) {}
	}, [ setDismissCapiModal ] );

	const setDismissAdsModal = useDismissAdsModalDispatch();
	const handleSetDismissAdsModal = useCallback( async () => {
		try {
			await setDismissAdsModal();
		} catch ( error ) {}
	}, [ setDismissAdsModal ] );

	useEffect( () => {
		openOnboardingModal();
	}, [ openOnboardingModal ] );

	useEffect( () => {
		openCapiModal();
	}, [ openCapiModal ] );

	return (
		<div className="pinterest-for-woocommerce-catalog-sync">
			<HealthCheck />
			<NavigationClassic />

			<div className="pinterest-for-woocommerce-catalog-sync__container">
				<SyncState />
				{ isAdCreditsNoticeOpen && adsCampaignIsActive && (
					<AdCreditsNotice />
				) }
				<SyncIssues />
			</div>
			{ isOnboardingModalOpen && (
				<OnboardingModals onCloseModal={ closeOnboardingModal } />
			) }
			{ isCapiModalOpen && (
				<CapiEnablementModal
					onCloseModal={ () => {
						hasClosedCapiModal.current = true;
						setIsCapiModalOpen( false );
					} }
					onDismiss={ handleSetDismissCapiModal }
				/>
			) }
		</div>
	);
};

export default CatalogSyncApp;
