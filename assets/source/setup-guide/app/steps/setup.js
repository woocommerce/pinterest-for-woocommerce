/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { compose } from '@wordpress/compose';
import { withDispatch, withSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardFooter,
	FlexItem,
	__experimentalText as Text
} from '@wordpress/components';
import {
	TextControl
} from '@woocommerce/components';

/**
 * Internal dependencies
 */
import { isEmailsListValid, sanitizeEmailsList } from '../helpers/emails';
import '../store';

const Setup = props => {

	const [debugEmails, setDebugEmails] = useState( props.pin4wc.debug_emails );

	useEffect(() => {
		if ( debugEmails !== props.pin4wc.debug_emails ) {
			setDebugEmails( props.pin4wc.debug_emails );
		}
	}, [props.pin4wc.debug_emails])

	const handleSubmit = async event => {
		const validEmails = isEmailsListValid( debugEmails );

		if ( ! validEmails ) {
			props.createNotice(
				'error',
				__(
					'Please check debug emails.',
					'pinterest-for-woocommerce'
				)
			);
			return false;
		}

		
		const update = await props.updateOptions( {
			[pin4wcSetupGuide.optionsName]: {
				...props.pin4wc,
				debug_emails: sanitizeEmailsList( debugEmails )
			}
		} );

		if ( update.success ) {
			props.createNotice(
				'success',
				__(
					'Debug emails were saved successfully.',
					'pinterest-for-woocommerce'
				)
			);
			props.goToNextStep();
		} else {
			props.createNotice(
				'error',
				__(
					'There was a problem saving your settings.',
					'pinterest-for-woocommerce'
				)
			);
		}

		event.preventDefault();
	}

	return (
		<div className="woocommerce-setup-guide__connect">
			<div className="woocommerce-setup-guide__step-header">
				<Text variant="title.small" as="h2">
					{ __( 'General Setup', 'pinterest-for-woocommerce' ) }
				</Text>
			</div>
			<form>
				<Card>
					<CardBody>
						<TextControl
							label={ __( 'Debug Emails', 'pinterest-for-woocommerce' ) }
							type="text"
							autoComplete="email"
							value={ debugEmails }
							onChange={ value => setDebugEmails( value ) }
						/>
						<Text variant="body">
							{ __( 'Insert one or more email addresses (comma separated) to be alerted when there is an error on data synchronization.', 'pinterest-for-woocommerce' ) }
						</Text>
					</CardBody>

					<CardFooter justify="center">
						<FlexItem>
							<div className="woocommerce-setup-guide__submit">
								<Button
									isPrimary
									onClick={ handleSubmit }
								>
									{ __(
										'Continue',
										'pinterest-for-woocommerce'
									) }
								</Button>
							</div>
						</FlexItem>
					</CardFooter>
				</Card>
			</form>
			<div className="woocommerce-setup-guide__footer">
				<Button
					isLink
					className="woocommerce-setup-guide__footer-link"
					onClick={ props.goToNextStep }
				>
					{ __(
						'Skip this step',
						'pinterest-for-woocommerce'
					) }
				</Button>
			</div>
		</div>
	)
}

export default compose(
	withSelect( select => {
		const { getOption } = select( 'pinterest/data' );

		return {
			pin4wc: getOption( pin4wcSetupGuide.optionsName ) || [],
		}
	}),
	withDispatch( dispatch => {
		const { createNotice } = dispatch( 'core/notices' );
		const { updateOptions } = dispatch( 'pinterest/data' );

		return {
			createNotice,
			updateOptions,
		};
	})
)(Setup);
