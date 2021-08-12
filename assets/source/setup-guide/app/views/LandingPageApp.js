/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { getNewPath, getHistory } from '@woocommerce/navigation';
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

const LandingPageApp = () => {
	const {
		faq_items: faqItems,
	} = wcSettings.pinterest_for_woocommerce.landing_page;

	const features = [
		{
			title: __( 'Sync your catalog', 'pinterest-for-woocommerce' ),
			text: __(
				'Connect your store to seamlessly sync your product catalog with Pinterest and create rich pins for each item. Your pins are kept up to date with daily automatic updates.',
				'pinterest-for-woocommerce'
			),
			image_url:
				wcSettings.pinterest_for_woocommerce.pluginUrl +
				'/assets/images/landing_connect.svg',
		},
		{
			title: __( 'Increase organic reach', 'pinterest-for-woocommerce' ),
			text: __(
				'Pinterest users can easily discover, save and buy products from your website without any advertising spend from you. Track your performance with the Pinterest tag.',
				'pinterest-for-woocommerce'
			),
			image_url:
				wcSettings.pinterest_for_woocommerce.pluginUrl +
				'/assets/images/landing_organic.svg',
		},
		{
			title: __(
				'Create a storefront on Pinterest',
				'pinterest-for-woocommerce'
			),
			text: __(
				'Syncing your catalog creates a Shop tab on your Pinterest profile which allows Pinterest users to easily discover your products.',
				'pinterest-for-woocommerce'
			),
			image_url:
				wcSettings.pinterest_for_woocommerce.pluginUrl +
				'/assets/images/landing_catalog.svg',
		},
	];

	return (
		<div className="pinterest-for-woocommerce-landing-page">
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
								{ __(
									'Get started',
									'pinterest-for-woocommerce'
								) }
							</Button>
						</Text>

						<Text variant="body">
							{ __(
								'By clicking ‘Get started’, you agree to our',
								'pinterest-for-woocommerce'
							) }{ ' ' }
							<a
								href="https://business.pinterest.com/business-terms-of-service/"
								target="_blank"
								rel="noreferrer"
							>
								{ __(
									'Terms of Service',
									'pinterest-for-woocommerce'
								) }
							</a>
							.
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

			<Card className="woocommerce-table pinterest-for-woocommerce-landing-page__features-section">
				<Flex justify="center" align="top">
					{ features.map( ( item, index ) => (
						<FlexBlock key={ index }>
							<img src={ item.image_url } alt="" />
							<Text variant="subtitle">{ item.title }</Text>
							<Text
								variant="body"
								dangerouslySetInnerHTML={ {
									__html: item.text,
								} }
							/>
							{ item?.extra && (
								<Text
									variant="body"
									className="extra"
									dangerouslySetInnerHTML={ {
										__html: item.extra,
									} }
								/>
							) }
						</FlexBlock>
					) ) }
				</Flex>
			</Card>

			<Card className="woocommerce-table pinterest-for-woocommerce-landing-page__faq-section">
				<Panel
					header={ __(
						'Frequently asked questions',
						'pinterest-for-woocommerce'
					) }
				>
					{ faqItems.map( ( item, index ) => (
						<PanelBody
							title={ item.question }
							initialOpen={ false }
							key={ index }
						>
							<PanelRow>{ item.answer }</PanelRow>
						</PanelBody>
					) ) }
				</Panel>
			</Card>
		</div>
	);
};

export default LandingPageApp;
