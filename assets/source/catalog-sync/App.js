/**
 * External dependencies
 */
import '@wordpress/notices';
import { useCallback, useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import SyncState from './sections/SyncState';
import SyncIssues from './sections/SyncIssues';
import TransientNotices from './components/TransientNotices';
import HealthCheck from '../setup-guide/app/components/HealthCheck';
import { useCreateNotice } from './helpers/effects';
import NavigationClassic from '../components/navigation-classic';
import AdsOnboardingModal from './components/AdsOnboardingModal';

/**
 * Catalog Sync Tab.
 *
 * @fires wcadmin_pfw_ with `{ link_id: 'ad-terms-of-service', context: 'wizard'|'settings' }`
 *
 * @return {JSX.Element} rendered component
 */
const CatalogSyncApp = () => {
	useCreateNotice( wcSettings.pinterest_for_woocommerce.error );
	const [ isAdsOnboardingModalOpen, setIsAdsOnboardingModalOpen ] = useState(
		false
	);

	const openAdsOnboardingModal = useCallback( () => {
		setIsAdsOnboardingModalOpen( true );
	}, [ setIsAdsOnboardingModalOpen ] );

	const closeAdsOnboardingModal = () => {
		setIsAdsOnboardingModalOpen( false );
	};

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
				<AdsOnboardingModal onCloseModal={ closeAdsOnboardingModal } />
			) }
		</div>
	);
};

export default CatalogSyncApp;
