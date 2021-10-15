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
import {
	useSettingsSelect,
	useSettingsDispatch,
	useCreateNotice,
} from '../../helpers/effects';
import './style.scss';

const HealthCheck = () => {
	const createNotice = useCreateNotice();
	const appSettings = useSettingsSelect();
	const [ healthStatus, setHealthStatus ] = useState();
	const [ noticeDismissed, setNoticeDismissed ] = useState( false );

	useEffect( () => {
		checkHealth();
	}, [] );

	const checkHealth = useCallback( async () => {
		try {
			// setHealthStatus();

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

	const disapprovalReasons = {
		MARKETPLACE: __(
			'Merchant is an affiliate or resale marketplace',
			'pinterest-for-woocommerce'
		),
		PROHIBITED_PRODUCTS: __(
			'Merchant does not meet our policy on prohibited products',
			'pinterest-for-woocommerce'
		),
		SERVICES: __(
			'Merchant offers services rather than products',
			'pinterest-for-woocommerce'
		),
		DOMAIN_AGE: __(
			"Merchant's domain age does not meet minimum requirement",
			'pinterest-for-woocommerce'
		),
		DOMAIN_MISMATCH: __(
			'Merchant domain mismatched with merchant account',
			'pinterest-for-woocommerce'
		),
		BROKEN_URL: __(
			"Merchant's URL is broken or requires registration",
			'pinterest-for-woocommerce'
		),
		INCOMPLETE: __(
			"Merchant's URL is incomplete or inaccessible",
			'pinterest-for-woocommerce'
		),
		NO_SHIPPING_POLICY: __(
			"Merchant's shipping policy is unclear or unavailable",
			'pinterest-for-woocommerce'
		),
		NO_RETURN_POLICY: __(
			"Merchant's returns policy is unclear or unavailable",
			'pinterest-for-woocommerce'
		),
		AUTHENTICITY: __(
			"Merchant's information is incomplete or plagiarized",
			'pinterest-for-woocommerce'
		),
		IN_STOCK: __(
			"Merchant's products are out of stock",
			'pinterest-for-woocommerce'
		),
		BANNER_ADS: __(
			"Merchant's website includes banner or pop-up ads",
			'pinterest-for-woocommerce'
		),
		IMAGE_QUALITY: __(
			"Merchant's products do not meet image quality requirements",
			'pinterest-for-woocommerce'
		),
		WATERMARKS: __(
			"Merchant's product images include watermarks",
			'pinterest-for-woocommerce'
		),
		SALE: __(
			"Merchant's products are always on sale",
			'pinterest-for-woocommerce'
		),
		OUT_OF_DATE: __(
			"Merchant's products refer to outdated content",
			'pinterest-for-woocommerce'
		),
		PRODUCT_DESCRIPTION: __(
			"Merchant's website uses generic product descriptions",
			'pinterest-for-woocommerce'
		),
		POP_UP: __(
			"Merchant's website displays several pop-up messages",
			'pinterest-for-woocommerce'
		),
		INAUTHENTIC_PHOTOS: __(
			"Merchant's product images are unavailable or mismatched",
			'pinterest-for-woocommerce'
		),
		RESALE_MARKETPLACE: __(
			'Resale marketplaces are not allowed',
			'pinterest-for-woocommerce'
		),
		AFFILIATE_MARKETPLACE: __(
			'Affiliate links are not allowed',
			'pinterest-for-woocommerce'
		),
		WEBSITE_REQUIREMENTS: __(
			'Account does not meet the website requirements for verification',
			'pinterest-for-woocommerce'
		),
		PRODUCT_REQUIREMENTS: __(
			'Account does not meet the product requirements for verification',
			'pinterest-for-woocommerce'
		),
		BRAND_REPUTATION: __(
			'Account does not meet the brand reputation criteria for verification',
			'pinterest-for-woocommerce'
		),
	};

	if (
		healthStatus === undefined ||
		( noticeDismissed && healthStatus.status === 'approved' )
	) {
		return null;
	}

	const notices = {
		approved: {
			status: 'success',
			message: __(
				'Your account is approved on Pinterest!',
				'pinterest-for-woocommerce'
			),
			dismissible: true,
			actions: [
				{ label: 'More information', url: 'https://example.com' },
				{ label: 'Cancel', onClick() {} },
				{ label: 'Submit', onClick() {}, variant: 'primary' },
			],
		},
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
				// translators: %s: campaign's name.
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
			onRemove={ () => setNoticeDismissed( true ) }
			actions={ notice.actions || [] }
		>
			<Text variant="subtitle">{ notice.message }</Text>

			{ notice.body && <Text variant="body">{ notice.body }</Text> }
		</Notice>
	);
};

export default HealthCheck;
