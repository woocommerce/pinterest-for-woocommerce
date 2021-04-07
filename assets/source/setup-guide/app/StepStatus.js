/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Icon,
	__experimentalText as Text
} from '@wordpress/components';
import { Spinner } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import CheckIcon from './helpers/check-icon';

const StepStatus = ({ label, status }) => {
	const icons = {
		pending: <Spinner />,
		error: <Icon icon='no-alt' />,
		success: <CheckIcon />
	}
	return (
		<div className={ `woocommerce-setup-guide__step-status has-${status}` }>
			<div class="woocommerce-setup-guide__step-status__icon">
				{ icons[ status ] }
			</div>
			<div class="woocommerce-setup-guide__step-status__label">
				<Text variant="body">{ label }</Text>
			</div>
		</div>
	);
}

export default StepStatus;
