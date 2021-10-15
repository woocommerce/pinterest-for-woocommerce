/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	Notice,
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis --- _experimentalText unlikely to change/disappear and also used by WC Core
} from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';
import { getNewPath } from '@woocommerce/navigation';
import {
	useEffect,
	useState,
	useCallback,
	createInterpolateElement,
} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import './style.scss';
import { useSettingsSelect, useCreateNotice } from '../../helpers/effects';

const HealthCheck = () => {
	const createNotice = useCreateNotice();
	const appSettings = useSettingsSelect();
	const [ healthStatus, setHealthStatus ] = useState();

	useEffect( () => {
		checkHealth();
	}, [] );

	const checkHealth = useCallback( async () => {
		try {
			const results = await apiFetch( {
				path:
					wcSettings.pinterest_for_woocommerce.apiRoute + '/health/',
				method: 'GET',
			} );

			setHealthStatus( results );
		} catch ( error ) {
			createNotice(
				'error',
				error.message ||
					__(
						'Couldn’t retrieve your linked business accounts.',
						'pinterest-for-woocommerce'
					)
			);
		}
	}, [ createNotice ] );

	if ( healthStatus === undefined || healthStatus.status === 'approved' ) {
		return null;
	}

	const notices = {
		pending: {
			status: 'warning',
			message: __(
				'Please hold on tight as your account is pending approval from Pinterest.',
				'pinterest-for-woocommerce'
			),
			dismissible: false,
		},
		declined: {
			status: 'error',
			message: sprintf(
				// translators: %s: The reason for disapproval.
				__(
					'Your merchant accound is disapproved. The reason was "%s"',
					'pinterest-for-woocommerce'
				),
				healthStatus.reason || '-'
			),
			body: __(
				'If you have a valid reason (such as having corrected the violations that resulted in the dissaproval) for appealing a merchant review decision, you can submit an appeal.',
				'pinterest-for-woocommerce'
			),
			dismissible: false,
			actions: [
				{
					label: __(
						'Submit an appeal',
						'pinterest-for-woocommerce'
					),
					url: addQueryArgs(
						wcSettings.pinterest_for_woocommerce.pinterestLinks
							.appealDeclinedMerchant,
						{ advertiserId: appSettings.tracking_advertiser }
					),
				},
			],
		},
		appeal_pending: {
			status: 'warning',
			dismissible: false,
			message: __(
				'Please hold on tight as there is an Appeal pending for your Pinterest account.',
				'pinterest-for-woocommerce'
			),
		},
		error: {
			status: 'error',
			dismissible: false,
		},
	};

	const notice = notices[ healthStatus.status ] || notices.error;

	if ( healthStatus.status === 'error' ) {
		notice.message =
			healthStatus.message ||
			__(
				'Could not fetch account status.',
				'pinterest-for-woocommerce'
			);
	}

	return (
		<Notice
			status={ notice.status }
			isDismissible={ notice.dismissible }
			actions={ notice.actions || [] }
			className="pinterest-for-woocommerce-healthcheck-notice"
		>
			<Text variant="titleSmall">
				{ notice.message }
			</Text>
			{ notice.body }
		</Notice>
	);
};

export default HealthCheck;
