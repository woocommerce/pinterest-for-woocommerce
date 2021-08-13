/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';

const PrelaunchNotice = () => {
	return (
		<Notice
			status="warning"
			isDismissible={ false }
			className="pinterest-for-woocommerce-prelaunch-notice"
		>
			<h3>
				{ __(
					'Pinterest for WooCommerce is a limited beta.',
					'pinterest-for-woocommerce'
				) }
			</h3>
			<p>
				{ __(
					'The integration is only available to approved stores participating in the beta program.',
					'pinterest-for-woocommerce'
				) }
			</p>
			<p>
				<a
					href="https://help.pinterest.com/en-gb/business/article/get-a-business-profile"
					target="_blank"
					rel="noreferrer"
				>
					{ __(
						'Click here for more information.',
						'pinterest-for-woocommerce'
					) }
				</a>
			</p>
		</Notice>
	);
};

export default PrelaunchNotice;
