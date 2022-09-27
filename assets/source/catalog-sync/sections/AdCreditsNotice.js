/**
 * External dependencies
 */
import { recordEvent } from '@woocommerce/tracks';
import { __ } from '@wordpress/i18n';
import { createInterpolateElement, useState } from '@wordpress/element';
import {
	ExternalLink,
	Icon,
	Notice,
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis --- _experimentalText unlikely to change/disappear and also used by WC Core
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useSettingsSelect } from '../../setup-guide/app/helpers/effects';
import GiftIcon from '../../setup-guide/app/components/GiftIcon';

/**
 * Closing the Ad Credits notice.
 *
 * @event wcadmin_pfw_ads_credits_success_notice
 */
/**
 * Clicking the "Add billing details" link.
 *
 * @event wcadmin_pfw_ads_billing_details_link_click
 */

/**
 * Catalog ad credits notice.
 *
 * @fires wcadmin_pfw_ads_credits_success_notice
 * @fires wcadmin_pfw_ads_billing_details_link_click
 * @return {JSX.Element} Rendered component.
 */
const AdCreditsNotice = () => {
	const [ isNoticeDisplayed, setIsNoticeDisplayed ] = useState( true );

	const appSettings = useSettingsSelect();
	const isBillingSetup = appSettings?.account_data?.is_billing_setup;
	const didRedeemCredits = appSettings?.account_data?.did_redeem_credits;
	const trackingAdvertiser = appSettings?.tracking_advertiser;

	const handleOnRemove = () => {
		setIsNoticeDisplayed( false );
		recordEvent( 'wcadmin_pfw_ads_credits_success_notice' );
	};

	return (
		isNoticeDisplayed && (
			<Notice
				status="success"
				isDismissible={ true }
				onRemove={ handleOnRemove }
				className="pinterest-for-woocommerce-catalog-sync__state__ad-credits"
			>
				<Icon
					icon={ GiftIcon }
					className="pinterest-for-woocommerce-catalog-sync__state__ad-credits__icon"
				/>
				{ isBillingSetup && didRedeemCredits ? (
					<Text>
						{ __(
							'You have successfully set up your account and claimed your free Pinterest ads credits.'
						) }
					</Text>
				) : (
					<Text>
						{ createInterpolateElement(
							__(
								'Get started with Pinterest ads with a free $125 ads credits. To claim the credits, <adsBillingDetails>add your billing details.</adsBillingDetails>',
								'pinterest-for-woocommerce'
							),
							{
								adsBillingDetails: trackingAdvertiser ? (
									<ExternalLink
										href={ `https://ads.pinterest.com/advertiser/${ trackingAdvertiser }/billing/` }
										onClick={ () => {
											recordEvent(
												'wcadmin_pfw_ads_credits_success_notice'
											);
										} }
									/>
								) : (
									<strong />
								),
							}
						) }
					</Text>
				) }
			</Notice>
		)
	);
};

export default AdCreditsNotice;
