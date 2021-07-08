/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, __experimentalText as Text } from '@wordpress/components';

const StepOverview = ({ title, description, link }) => {
	return (
		<div className="woocommerce-setup-guide__step-overview">
			{title && (
				<div className="woocommerce-setup-guide__step-overview__title">
					<Text variant="subtitle">{title}</Text>
				</div>
			)}

			{description && (
				<div className="woocommerce-setup-guide__step-overview__description">
					<Text variant="body">{description}</Text>
				</div>
			)}

			{link && (
				<div className="woocommerce-setup-guide__step-overview__link">
					<Button isLink href={link} target="_blank">
						{__('Read more')}
					</Button>
				</div>
			)}
		</div>
	);
};

export default StepOverview;
