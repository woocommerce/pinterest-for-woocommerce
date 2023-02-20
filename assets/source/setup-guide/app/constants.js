/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Enum of general label status.
 *
 * @readonly
 * @enum {string}
 */
export const LABEL_STATUS = Object.freeze( {
	PENDING: 'pending',
	SUCCESS: 'success',
} );

/**
 * Enum of general process status.
 *
 * @readonly
 * @enum {string}
 */
export const PROCESS_STATUS = Object.freeze( {
	...LABEL_STATUS,
	IDLE: 'idle',
	ERROR: 'error',
} );

/**
 * Enum of the disapproval reasons for merchants.
 */
export const DISAPPROVAL_COPY_STATES = Object.freeze( {
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
	AUTHENTICITY_NO_SOCIALS_OR_ABOUT: __(
		"There is no 'About Us' page or no social information in your website",
		'pinterest-for-woocommerce'
	),
	AUTHENTICITY_NO_CONTACT_INFORMATION: __(
		'There is no contact information in your website',
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
	INCOMPLETE_WEBSITE_TEMPLATE: __(
		'The template of the website is incomplete',
		'pinterest-for-woocommerce'
	),
} );
