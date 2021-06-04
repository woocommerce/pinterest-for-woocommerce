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
			title: 'Some headline here',
			text: 'Some text here some text here some text heresome text heresome text here some text here some text here some text here some text here',
			imageUrl: 'https://placehold.it/100x100/'
		},
		{
			title: 'Some headline here',
			text: 'Some text here some text here some text heresome text heresome text here some text here some text here some text here some text here',
			imageUrl: 'https://placehold.it/100x100/'
		},
		{
			title: 'Some headline here',
			text: 'Some text here some text here some text heresome text heresome text here some text here some text here some text here some text here',
			imageUrl: 'https://placehold.it/100x100/'
		},
	];

	return (
		<div className="pin4wc-landing-page">
			<Card className="woocommerce-table pin4wc-landing-page__welcome-section">
				<Flex>
					<FlexBlock className="content-block">
						<Text variant="title.medium">List your products on Pinterest, headline here</Text>

						<Text variatn="body">Some text here some text here some text heresome text heresome text here some text here some text here some text here some text here</Text>

						<Text variatn="body">
							<Button
								isPrimary
								onClick={() => updateQueryString({ view: 'wizard' } ) }
							>
								{ __( 'Get started', 'pinterest-for-woocommerce' ) }
							</Button>
						</Text>

						<Text variant="body">By clicking ‘Get started’, you agree to our <a href="#" target="_blank">Terms of Service</a>.</Text>
					</FlexBlock>
					<FlexBlock className="image-block">
						<img src="https://placehold.it/416x300/" />
					</FlexBlock>
				</Flex>
			</Card>

			<Card className="woocommerce-table pin4wc-landing-page__features-section">
				<Flex justify="center">
					{
						features.map( item => (
							<FlexBlock>
								<img src={ item.imageUrl } />
								<Text variant="subtitle">{ item.title }</Text>
								<Text variant="body">{ item.text }</Text>
							</FlexBlock>
						))
					}
				</Flex>
			</Card>

			<Card className="woocommerce-table pin4wc-landing-page__faq-section">
				<Panel header={ __( 'Frequently asked questions', 'pinterest-for-woocommerce' ) }>
					<PanelBody title="Some question here?" initialOpen={ true }>
						<PanelRow>Some text here some text here some text heresome text heresome text here some text here some text here some text here some text here Some text here some text here some text heresome text heresome text here some text here some text here some text here some text here Some text here some text here some text heresome text heresome text here some text here some text here some text here some text here</PanelRow>
					</PanelBody>
					<PanelBody title="Question 2 here?" initialOpen={ false }>
						<PanelRow>Some text here some text here some text heresome text heresome text here some text here some text here some text here some text here Some text here some text here some text heresome text heresome text here some text here some text here some text here some text here Some text here some text here some text heresome text heresome text here some text here some text here some text here some text here</PanelRow>
					</PanelBody>
					<PanelBody title="Question 3 here?" initialOpen={ false }>
						<PanelRow>Some text here some text here some text heresome text heresome text here some text here some text here some text here some text here Some text here some text here some text heresome text heresome text here some text here some text here some text here some text here Some text here some text here some text heresome text heresome text here some text here some text here some text here some text here</PanelRow>
					</PanelBody>
				</Panel>
			</Card>
		</div>
	);
};

export default LandingPageApp;
