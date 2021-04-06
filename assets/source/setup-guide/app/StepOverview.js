/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	ExternalLink,
	__experimentalText as Text
} from '@wordpress/components';

const StepOverview = ({ title, description, link }) => {
	return (
		<div className="woocommerce-setup-guide__step-overview">
			{ title &&
				<div class="woocommerce-setup-guide__step-overview__title">
					<Text variant="subtitle">{ title }</Text>
				</div>
			}

			{ description &&
				<div class="woocommerce-setup-guide__step-overview__description">
					<Text variatn="body">{ description }</Text>
				</div>
			}

			{ link &&
				<div class="woocommerce-setup-guide__step-overview__link">
					<ExternalLink href={ link }>{ __( 'Read more' ) }</ExternalLink>
				</div>
			}
		</div>
	);
}

export default StepOverview;
