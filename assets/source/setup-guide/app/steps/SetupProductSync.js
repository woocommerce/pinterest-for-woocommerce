/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CheckboxControl,
	__experimentalText as Text,
} from '@wordpress/components';
import { Spinner } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import StepOverview from '../components/StepOverview';
import {
	useSettingsSelect,
	useSettingsDispatch,
	useCreateNotice,
} from '../helpers/effects';

const SetupProductSync = ( { goToNextStep, view } ) => {
	const appSettings = useSettingsSelect();
	const setAppSettings = useSettingsDispatch( view === 'wizard' );
	const createNotice = useCreateNotice();

	const handleOptionChange = async ( name, value ) => {
		const update = await setAppSettings( {
			[ name ]: value ?? ! appSettings[ name ],
		} );

		if ( ! update.success ) {
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
		<div className="woocommerce-setup-guide__setup-product-sync">
			<div className="woocommerce-setup-guide__step-columns">
				<div className="woocommerce-setup-guide__step-column">
					<StepOverview
						title={ __(
							'Set up Product Sync',
							'pinterest-for-woocommerce'
						) }
						description={ __(
							'Use description text to help users understand more',
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
											'Product Sync',
											'pinterest-for-woocommerce'
										) }
									</Text>
									<CheckboxControl
										label={ __(
											'Enable Product Sync',
											'pinterest-for-woocommerce'
										) }
										checked={
											appSettings.product_sync_enabled
										}
										className="woocommerce-setup-guide__checkbox-group"
										onChange={ () =>
											handleOptionChange(
												'product_sync_enabled'
											)
										}
									/>
								</>
							) : (
								<Spinner />
							) }
						</CardBody>
					</Card>

					{ view === 'wizard' && (
						<div className="woocommerce-setup-guide__footer-button">
							<Button
								isPrimary
								onClick={ goToNextStep }
							>
								{ __(
										'Continue',
										'pinterest-for-woocommerce'
								  ) }
							</Button>
						</div>
					) }
				</div>
			</div>
		</div>
	);
};

export default SetupProductSync;
