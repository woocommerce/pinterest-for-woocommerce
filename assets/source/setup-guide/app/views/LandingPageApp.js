/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { getNewPath, getHistory } from '@woocommerce/navigation';
import { createInterpolateElement } from '@wordpress/element';
import {
	Button,
	Card,
	Flex,
	FlexBlock,
	Panel,
	PanelBody,
	PanelRow,
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis --- _experimentalText unlikely to change/disappear and also used by WC Core
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import PrelaunchNotice from '../../../components/prelaunch-notice';

const WelcomeSection = () => {
	return (
		<Card className="woocommerce-table pinterest-for-woocommerce-landing-page__welcome-section">
			<Flex>
				<FlexBlock className="content-block">
					<Text variant="title.medium">
						{ __(
							'Get your products in front of more than 475M people on Pinterest',
							'pinterest-for-woocommerce'
						) }
					</Text>

					<Text variant="body">
						{ __(
							'Pinterest is a visual discovery engine people use to find inspiration for their lives! More than 475 million people have saved more than 300 billion Pins, making it easier to turn inspiration into their next purchase.',
							'pinterest-for-woocommerce'
						) }
					</Text>

					<Text variant="body">
						<Button
							isPrimary
							onClick={ () =>
								getHistory().push(
									getNewPath(
										{},
										wcSettings.pinterest_for_woocommerce
											.isSetupComplete
											? '/pinterest/catalog'
											: '/pinterest/onboarding'
									)
								)
							}
						>
							{ __( 'Get started', 'pinterest-for-woocommerce' ) }
						</Button>
					</Text>

					<Text variant="body">
						{ createInterpolateElement(
							__(
								'By clicking ‘Get started’, you agree to our <a>Terms of Service</a>.',
								'pinterest-for-woocommerce'
							),
							{
								a: (
									// Disabling no-content rule - content is interpolated from above string.
									// eslint-disable-next-line jsx-a11y/anchor-has-content
									<a
										href="https://business.pinterest.com/business-terms-of-service/"
										target="_blank"
										rel="noreferrer"
									/>
								),
							}
						) }
					</Text>
				</FlexBlock>
				<FlexBlock className="image-block">
					<img
						src={
							wcSettings.pinterest_for_woocommerce.pluginUrl +
							'/assets/images/landing_welcome.png'
						}
						alt=""
					/>
				</FlexBlock>
			</Flex>
		</Card>
	);
};

const FeaturesSection = () => {
	return (
		<Card className="woocommerce-table pinterest-for-woocommerce-landing-page__features-section">
			<Flex justify="center" align="top">
				<Feature
					imageUrl={
						wcSettings.pinterest_for_woocommerce.pluginUrl +
						'/assets/images/landing_connect.svg'
					}
					title={ __(
						'Sync your catalog',
						'pinterest-for-woocommerce'
					) }
					text={ __(
						'Connect your store to seamlessly sync your product catalog with Pinterest and create rich pins for each item. Your pins are kept up to date with daily automatic updates.',
						'pinterest-for-woocommerce'
					) }
				/>
				<Feature
					imageUrl={
						wcSettings.pinterest_for_woocommerce.pluginUrl +
						'/assets/images/landing_organic.svg'
					}
					title={ __(
						'Increase organic reach',
						'pinterest-for-woocommerce'
					) }
					text={ __(
						'Pinterest users can easily discover, save and buy products from your website without any advertising spend from you. Track your performance with the Pinterest tag.',
						'pinterest-for-woocommerce'
					) }
				/>
				<Feature
					imageUrl={
						wcSettings.pinterest_for_woocommerce.pluginUrl +
						'/assets/images/landing_catalog.svg'
					}
					title={ __(
						'Create a storefront on Pinterest',
						'pinterest-for-woocommerce'
					) }
					text={ __(
						'Syncing your catalog creates a Shop tab on your Pinterest profile which allows Pinterest users to easily discover your products.',
						'pinterest-for-woocommerce'
					) }
				/>
			</Flex>
		</Card>
	);
};

const Feature = ( { title, text, imageUrl } ) => {
	return (
		<FlexBlock>
			<img src={ imageUrl } alt="" />
			<Text variant="subtitle">{ title }</Text>
			<Text variant="body">{ text }</Text>
		</FlexBlock>
	);
};

const FaqSection = () => {
	return (
		<Card className="woocommerce-table pinterest-for-woocommerce-landing-page__faq-section">
			<Panel
				header={ __(
					'Frequently asked questions',
					'pinterest-for-woocommerce'
				) }
			>
				<FaqQuestion
					question={ __(
						'Why am I getting an “Account not connected” error message?',
						'pinterest-for-woocommerce'
					) }
					answer={ __(
						'Your password might have changed recently. Click Reconnect Pinterest Account and follow the instructions on screen to restore the connection.',
						'pinterest-for-woocommerce'
					) }
				/>
				<FaqQuestion
					question={ __(
						'I have more than one Pinterest Advertiser account. Can I connect my WooCommerce store to multiple Pinterest Advertiser accounts?',
						'pinterest-for-woocommerce'
					) }
					answer={ __(
						'Only one Pinterest advertiser account can be linked to each WooCommerce store. If you want to connect a different Pinterest advertiser account you will need to either Disconnect the existing Pinterest Advertiser account from your current WooCommerce store and connect a different Pinterest Advertiser account, or Create another WooCommerce store and connect the additional Pinterest Advertiser account.',
						'pinterest-for-woocommerce'
					) }
				/>
			</Panel>
		</Card>
	);
};

const FaqQuestion = ( { question, answer } ) => {
	return (
		<PanelBody title={ question } initialOpen={ false }>
			<PanelRow>{ answer }</PanelRow>
		</PanelBody>
	);
};

const LandingPageApp = () => {
	const { pluginVersion } = wcSettings.pinterestForWooCommerce;

	// Only show the pre-launch beta notice if the plugin version is a beta.
	const prelaunchNotice = pluginVersion.includes( 'beta' ) ? (
		<PrelaunchNotice />
	) : null;

	return (
		<div className="pinterest-for-woocommerce-landing-page">
			{ prelaunchNotice }
			<WelcomeSection />
			<FeaturesSection />
			<FaqSection />
		</div>
	);
};

export default LandingPageApp;
