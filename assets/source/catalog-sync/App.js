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
import OnboardingModal from './components/OnboardingModal';

const CatalogSyncApp = () => {
	useCreateNotice( wcSettings.pinterest_for_woocommerce.error );
	const [
		isOnboardingAdCreditsModalOpen,
		setIsOnboardingAdCreditsModalOpen,
	] = useState( false );

	const openOnboardingAdCreditsModal = useCallback( () => {
		setIsOnboardingAdCreditsModalOpen( true );
	}, [ setIsOnboardingAdCreditsModalOpen ] );

	const closeOnboardingAdCreditsModal = () => {
		setIsOnboardingAdCreditsModalOpen( false );
	};

	useEffect( () => {
		openOnboardingAdCreditsModal();
	}, [ openOnboardingAdCreditsModal ] );

	return (
		<div className="pinterest-for-woocommerce-catalog-sync">
			<HealthCheck />
			<NavigationClassic />

			<TransientNotices />
			<div className="pinterest-for-woocommerce-catalog-sync__container">
				<SyncState />
				<SyncIssues />
			</div>
			{ isOnboardingAdCreditsModalOpen && (
				<OnboardingModal
					onCloseModal={ closeOnboardingAdCreditsModal }
				/>
			) }
		</div>
	);
};

export default CatalogSyncApp;
