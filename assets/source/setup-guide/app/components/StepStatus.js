/**
 * External dependencies
 */
import { Spinner } from '@woocommerce/components';
import { Icon, __experimentalText as Text } from '@wordpress/components'; // eslint-disable-line @wordpress/no-unsafe-wp-apis --- _experimentalText unlikely to change/disappear and also used by WC Core

/**
 * Internal dependencies
 */
import CheckIcon from '../helpers/check-icon';

const StepStatus = ( { label, status } ) => {
	const icons = {
		pending: <Spinner />,
		error: <Icon icon="no-alt" />,
		success: <CheckIcon />,
	};

	return (
		<div
			className={ `woocommerce-setup-guide__step-status has-${ status }` }
		>
			<div className="woocommerce-setup-guide__step-status__icon">
				{ icons[ status ] }
			</div>
			<div className="woocommerce-setup-guide__step-status__label">
				<Text variant={ status === 'success' ? 'subtitle' : 'body' }>
					{ label }
				</Text>
			</div>
		</div>
	);
};

export default StepStatus;
