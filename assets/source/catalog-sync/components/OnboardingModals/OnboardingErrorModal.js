/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Flex,
	Dashicon,
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis --- _experimentalText unlikely to change/disappear and also used by WC Core
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import OnboardingModal from './OnboardingModal';
import { useSettingsSelect } from '../../../setup-guide/app/helpers/effects';

/**
 * Ads Onboarding Modal.
 *
 * @param {Object} options
 * @param {Function} options.onCloseModal Action to call when the modal gets closed.
 *
 * @return {JSX.Element} rendered component
 */
const OnboardingErrorModal = ( { onCloseModal } ) => {
	const couponRedeemInfo =
		useSettingsSelect()?.account_data?.coupon_redeem_info;

	let errorMessageText = '';
	switch ( couponRedeemInfo?.error_code ) {
		// case 2327:
			
		// 	break;
	
		default:
			errorMessageText = couponRedeemInfo?.error_message;
			break;
	}
	return (
		<OnboardingModal onCloseModal={ onCloseModal }>
			<Flex
				direction="row"
				className="pinterest-for-woocommerce-catalog-sync__onboarding-generic-modal__error"
			>
				<Dashicon icon="info" />
				<Text variant="body.large">{ errorMessageText }</Text>
			</Flex>
		</OnboardingModal>
	);
};

export default OnboardingErrorModal;
