/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import {
	useEffect,
	useState,
	useCallback,
	createInterpolateElement,
} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Icon, external as externalIcon } from '@wordpress/icons';
import {
	Button,
	Flex,
	Modal,
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis --- _experimentalText unlikely to change/disappear and also used by WC Core
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useSettingsSelect } from '../../setup-guide/app/helpers/effects';

const OnboardingModal = ( { isBillingSetup, onCloseModal } ) => {
	const appSettings = useSettingsSelect();

	return (
		<Modal
			icon={
				<img
					src={
						wcSettings.pinterest_for_woocommerce.pluginUrl +
						'/assets/images/gift_banner.svg'
					}
					alt="Gift banner"
				/>
			}
			onRequestClose={ closeConfirmationModal }
			className="pinterest-for-woocommerce-catalog-sync__onboarding-modal"
		>
			<Text variant="title.small">
				{ __(
					'You are one step away from claiming $125 of Pinterest ad credits.',
					'pinterest-for-woocommerce'
				) }
			</Text>
			<Text variant="body">
				{ __(
					'You have successfully set up your Pinterest integration! Your product catalog is being synced and reviewed. This could take up to 2 days.',
					'pinterest-for-woocommerce'
				) }
			</Text>
			<Text variant="body">
				{ __(
					'You are eligible for $125 of Pinterest ad credits. To claim the credits, ',
					'pinterest-for-woocommerce'
				) }
				<strong>
					{ __(
						'you would need to add your billing details and spend $15 on Pinterest ads.',
						'pinterest-for-woocommerce'
					) }
				</strong>
			</Text>
			<Flex direction="row" justify="flex-end">
				{ isBillingSetup ? (
					<Button isPrimary onClick={ onCloseModal }>
						{ __( 'Got it', 'pinterest-for-woocommerce' ) }
					</Button>
				) : (
					<>
						<Button onClick={ onCloseModal }>
							{ __(
								'Do this later',
								'pinterest-for-woocommerce'
							) }
						</Button>
						{
							// Empty tracking_advertiser should not happen.
							appSettings.tracking_advertiser ? (
								<Button
									isPrimary
									href={ `https://ads.pinterest.com/advertiser/${ appSettings.tracking_advertiser }/billing/` }
									target="_blank"
								>
									{ __(
										'Add billing details',
										'pinterest-for-woocommerce'
									) }
									<Icon icon={ externalIcon } />
								</Button>
							) : (
								''
							)
						}
					</>
				) }
			</Flex>
		</Modal>
	);
};

export default OnboardingModal;
