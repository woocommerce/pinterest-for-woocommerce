/**
 * External dependencies
 */
import '@wordpress/notices';
import { useSelect } from '@wordpress/data';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { recordEvent } from '@woocommerce/tracks';

/**
 * Internal dependencies
 */
import SyncState from './sections/SyncState';
import SyncIssues from './sections/SyncIssues';
import TransientNotices from './components/TransientNotices';
import HealthCheck from '../setup-guide/app/components/HealthCheck';
import { useCreateNotice, useDismissAdsModalDispatch } from './helpers/effects';
import NavigationClassic from '../components/navigation-classic';
import AdsOnboardingModal from './components/AdsOnboardingModal';
import { USER_INTERACTION_STORE_NAME } from './data';

/**
 * Opening a modal.
 *
 * @event wcadmin_pfw_modal_open
 * @property {string} name Ads Onboarding Modal.
 * @property {string} context catalog-sync
 */
/**
 * Closing a modal.
 *
 * @event wcadmin_pfw_modal_closed
 * @property {string} name Ads Onboarding Modal.
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
	useCreateNotice( wcSettings.pinterest_for_woocommerce.error );
	const [ isAdsOnboardingModalOpen, setIsAdsOnboardingModalOpen ] = useState(
		false
	);

	const userInteractions = useSelect( ( select ) =>
		select( USER_INTERACTION_STORE_NAME ).getUserInteractions()
	);

	const userInteractionsLoaded = useSelect( ( select ) =>
		select( USER_INTERACTION_STORE_NAME ).areInteractionsLoaded()
	);

	const openAdsOnboardingModal = useCallback( () => {
		if (
			userInteractionsLoaded === false ||
			userInteractions?.ads_modal_dismissed
		) {
			return;
		}

		setIsAdsOnboardingModalOpen( true );
		recordEvent( 'pfw_modal_open', {
			context: 'catalog-sync',
			name: 'ads-credits-onboarding',
		} );
	}, [ userInteractions?.ads_modal_dismissed, userInteractionsLoaded ] );

	const closeAdsOnboardingModal = () => {
		setIsAdsOnboardingModalOpen( false );
		recordEvent( 'pfw_modal_closed', {
			context: 'catalog-sync',
			name: 'ads-credits-onboarding',
		} );
	};

	const setDismissAdsModal = useDismissAdsModalDispatch();
	const handleSetDismissAdsModal = useCallback( async () => {
		try {
			await setDismissAdsModal();
		} catch ( error ) {}
	}, [ setDismissAdsModal ] );

	const doItLaterAdsOnboardingModal = useCallback( () => {
		setIsAdsOnboardingModalOpen( false );
		handleSetDismissAdsModal();
		recordEvent( 'pfw_modal_close', {
			context: 'catalog-sync',
			name: 'ads-credits-onboarding-do-it-later',
		} );
	}, [ setIsAdsOnboardingModalOpen, handleSetDismissAdsModal ] );

	useEffect( () => {
		openAdsOnboardingModal();
	}, [ openAdsOnboardingModal ] );

	return (
		<div className="pinterest-for-woocommerce-catalog-sync">
			<HealthCheck />
			<NavigationClassic />

			<TransientNotices />
			<div className="pinterest-for-woocommerce-catalog-sync__container">
				<SyncState />
				<SyncIssues />
			</div>
			{ isAdsOnboardingModalOpen && (
				<AdsOnboardingModal
					onCloseModal={ closeAdsOnboardingModal }
					onDoItLater={ doItLaterAdsOnboardingModal }
				/>
			) }
		</div>
	);
};

export default CatalogSyncApp;
