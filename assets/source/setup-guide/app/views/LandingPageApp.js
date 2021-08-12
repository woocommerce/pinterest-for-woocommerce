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
		welcome,
		features,
		faq_items: faqItems,
	} = wcSettings.pinterest_for_woocommerce.landing_page;

	return (
		<div className="pin4wc-landing-page">
			<Card className="woocommerce-table pin4wc-landing-page__welcome-section">
				<Flex>
					<FlexBlock className="content-block">
						<Text variant="title.medium">{ welcome.title }</Text>

						<Text variant="body">{ welcome.text }</Text>

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
								href={ welcome.tos_link }
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
						<img src={ welcome.image_url } alt="" />
					</FlexBlock>
				</Flex>
			</Card>

			<Card className="woocommerce-table pin4wc-landing-page__features-section">
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

			<Card className="woocommerce-table pin4wc-landing-page__faq-section">
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
