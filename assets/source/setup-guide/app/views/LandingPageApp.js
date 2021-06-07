/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	Flex,
	FlexBlock,
	Panel,
	PanelBody,
	PanelRow,
	__experimentalText as Text
} from '@wordpress/components';
import {
	updateQueryString,
} from '@woocommerce/navigation';

/**
 * Internal dependencies
 */

const LandingPageApp = () => {
	const features = [
		{
			title: __( 'Connect your account', 'pinterest-for-woocommerce' ),
			text: __( 'Install the Pinterest for WooCommerce app to quickly upload your product catalog and publish Pins for items you sell. Track performance with the Pinterest Tag and keep your Pins up to date with our daily automatic updates.', 'pinterest-for-woocommerce' ),
			imageUrl: 'https://placehold.it/100x100/'
		},
		{
			title: __( 'Increase organic reach', 'pinterest-for-woocommerce' ),
			text: __( 'Once you\'ve uploaded your catalog, people on Pinterest can easily discover, save and buy products from your website without any advertising spend from you.*', 'pinterest-for-woocommerce' ),
			extra: __( '*It can take up to 5 business days for the product catalog to sync for this first time' ),
			imageUrl: 'https://placehold.it/100x100/'
		},
		{
			title: __( 'Merchant storefronts on profile', 'pinterest-for-woocommerce' ),
			text: __( 'Upload your catalog via the WooCommerce for Pinterest app and transform the shop tab on your business profile into an inspiring storefront. Pinners will see featured product groups and dynamically created recommendations and can easily navigate by category. Whenever they click on your profile, they\'ll be automatically taken to your storefront.', 'pinterest-for-woocommerce' ),
			imageUrl: 'https://placehold.it/100x100/'
		},
	];

	const faqItems = [
		{
			question: __( 'Why am I getting an “Account not connected” error message?', 'pinterest-for-woocommerce' ),
			answer: __( 'Your password might have changed recently. Click Reconnect Pinterest Account and follow the instructions on screen to restore the connection.', 'pinterest-for-woocommerce' ),
		},
		{
			question: __( 'I have more than one Pinterest Advertiser account. Can I connect my WooCommerce store to multiple Pinterest Advertiser accounts?', 'pinterest-for-woocommerce' ),
			answer: __( 'Only one Pinterest advertiser account can be linked to each WooCommerce store. If you want to connect a different Pinterest advertiser account you will need to either: Disconnect the existing Pinterest Advertiser account from your current WooCommerce store and connect a different Pinterest Advertiser account Create another WooCommerce store and connect the additional Pinterest Advertiser account.', 'pinterest-for-woocommerce' ),
		}
	]

	return (
		<div className="pin4wc-landing-page">
			<Card className="woocommerce-table pin4wc-landing-page__welcome-section">
				<Flex>
					<FlexBlock className="content-block">
						<Text variant="title.medium">{ __( 'Get your products in front of more than 475M people on Pinterest', 'pinterest-for-woocommerce' ) }</Text>

						<Text variatn="body">{ __( 'Pinterest is a visual discovery engine people use to find inspiration for their lives and make it easier to shop for home decor, fashion and style, electronics and more. 400 million people have saved more than 300 billion Pins across a range of interests, which others with similar tastes can discover through search and recommendations.', 'pinterest-for-woocommerce' ) }</Text>

						<Text variatn="body">
							<Button
								isPrimary
								onClick={() => updateQueryString({ view: 'wizard' } ) }
							>
								{ __( 'Get started', 'pinterest-for-woocommerce' ) }
							</Button>
						</Text>

						<Text variant="body">{ __( 'By clicking ‘Get started’, you agree to our', 'pinterest-for-woocommerce' ) } <a href="https://business.pinterest.com/business-terms-of-service/" target="_blank">{ __( 'Terms of Service', 'pinterest-for-woocommerce' ) }</a>.</Text>
					</FlexBlock>
					<FlexBlock className="image-block">
						<img src="https://placehold.it/416x300/" />
					</FlexBlock>
				</Flex>
			</Card>

			<Card className="woocommerce-table pin4wc-landing-page__features-section">
				<Flex justify="center" align="top">
					{
						features.map( (item, index) => (
							<FlexBlock key={index}>
								<img src={ item.imageUrl } />
								<Text variant="subtitle">{ item.title }</Text>
								<Text variant="body">{ item.text }</Text>
								{ item?.extra && <Text variant="body" className="extra">{ item.text }</Text> }
							</FlexBlock>
						))
					}
				</Flex>
			</Card>

			<Card className="woocommerce-table pin4wc-landing-page__faq-section">
				<Panel header={ __( 'Frequently asked questions', 'pinterest-for-woocommerce' ) }>
					{
						faqItems.map( ( item, index ) => (
							<PanelBody title={item.question } initialOpen={ index === 0 } key={ index }>
								<PanelRow>{item.answer}</PanelRow>
							</PanelBody>
						))
					}
				</Panel>
			</Card>
		</div>
	);
};

export default LandingPageApp;
