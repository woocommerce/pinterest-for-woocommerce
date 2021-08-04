/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Spinner } from '@woocommerce/components';
import {
	Button,
	Card,
	CardBody,
	CheckboxControl,
	Icon,
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis --- _experimentalText unlikely to change/disappear and also used by WC Core
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import StepOverview from '../components/StepOverview';
import {
	useSettingsSelect,
	useSettingsDispatch,
	useCreateNotice,
} from '../helpers/effects';

const SetupPins = ( {} ) => {
	const appSettings = useSettingsSelect();
	const setAppSettings = useSettingsDispatch( false );
	const createNotice = useCreateNotice();

	const handleOptionChange = async ( name, value ) => {
		try {
			await setAppSettings( {
				[ name ]: value ?? ! appSettings[ name ],
			} );
		} catch ( error ) {
			createNotice(
				'error',
				__(
					'There was a problem saving your settings.',
					'pinterest-for-woocommerce'
				)
			);
		}
	};

	return (
		<div className="woocommerce-setup-guide__setup-pins">
			<div className="woocommerce-setup-guide__step-columns">
				<div className="woocommerce-setup-guide__step-column">
					<StepOverview
						title={ __(
							'Publish Pins and Rich Pins',
							'pinterest-for-woocommerce'
						) }
						description={ __(
							'Rich Pins are a type of organic Pin that automatically sync information from your website to your Pins. You can identify Rich Pins by the extra information above and below the image on closeup and the bold title in your feed. If something changes on the original website, the Rich Pin updates to reflect that change.',
							'pinterest-for-woocommerce'
						) }
					/>
				</div>
				<div className="woocommerce-setup-guide__step-column">
					<Card>
						<CardBody size="large">
							{ undefined !== appSettings &&
							Object.keys( appSettings ).length > 0 ? (
								<>
									<Text
										className="woocommerce-setup-guide__checkbox-heading"
										variant="subtitle"
									>
										{ __(
											'Tracking',
											'pinterest-for-woocommerce'
										) }
									</Text>
									<CheckboxControl
										label={ __(
											'Track conversions',
											'pinterest-for-woocommerce'
										) }
										checked={
											appSettings.track_conversions
										}
										className="woocommerce-setup-guide__checkbox-group"
										onChange={ () =>
											handleOptionChange(
												'track_conversions'
											)
										}
									/>
									<CheckboxControl
										label={ __(
											'Enhanced Match support',
											'pinterest-for-woocommerce'
										) }
										help={
											<Button
												isLink
												href={
													wcSettings.pin4wc
														.pinterestLinks
														.enhancedMatch
												}
												target="_blank"
											>
												<Icon icon="editor-help" />
											</Button>
										}
										checked={
											appSettings.enhanced_match_support
										}
										className="woocommerce-setup-guide__checkbox-group"
										onChange={ () =>
											handleOptionChange(
												'enhanced_match_support'
											)
										}
									/>
									<Text
										className="woocommerce-setup-guide__checkbox-heading"
										variant="subtitle"
									>
										{ __(
											'Rich Pins',
											'pinterest-for-woocommerce'
										) }
									</Text>
									<CheckboxControl
										label={ __(
											'Add Rich Pins for Products',
											'pinterest-for-woocommerce'
										) }
										checked={
											appSettings.rich_pins_on_products
										}
										className="woocommerce-setup-guide__checkbox-group"
										onChange={ () =>
											handleOptionChange(
												'rich_pins_on_products'
											)
										}
									/>
									<CheckboxControl
										label={ __(
											'Add Rich Pins for Posts',
											'pinterest-for-woocommerce'
										) }
										checked={
											appSettings.rich_pins_on_posts
										}
										className="woocommerce-setup-guide__checkbox-group"
										onChange={ () =>
											handleOptionChange(
												'rich_pins_on_posts'
											)
										}
									/>
									<Text
										className="woocommerce-setup-guide__checkbox-heading"
										variant="subtitle"
									>
										{ __(
											'Save to Pinterest',
											'pinterest-for-woocommerce'
										) }
									</Text>
									<CheckboxControl
										label={ __(
											'Save to Pinterest',
											'pinterest-for-woocommerce'
										) }
										checked={
											appSettings.save_to_pinterest
										}
										className="woocommerce-setup-guide__checkbox-group"
										onChange={ () =>
											handleOptionChange(
												'save_to_pinterest'
											)
										}
									/>
								</>
							) : (
								<Spinner />
							) }
						</CardBody>
					</Card>
				</div>
			</div>
		</div>
	);
};

export default SetupPins;
