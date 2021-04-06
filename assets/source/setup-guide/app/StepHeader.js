/**
 * WordPress dependencies
 */
import { __experimentalText as Text } from '@wordpress/components';

const StepHeader = ({ title, subtitle, description }) => {
	return (
		<div className="woocommerce-setup-guide__step-header">
			{ subtitle &&
				<div class="woocommerce-setup-guide__step-header__subtitle">
					<Text variant="subtitle.small">{ subtitle }</Text>
				</div>
			}

			{ title &&
				<div class="woocommerce-setup-guide__step-header__title">
					<Text variant="title.large">{ title }</Text>
				</div>
			}

			{ description &&
				<div class="woocommerce-setup-guide__step-header__description">
					<Text variant="body">{ description }</Text>
				</div>
			}
		</div>
	);
}

export default StepHeader;
