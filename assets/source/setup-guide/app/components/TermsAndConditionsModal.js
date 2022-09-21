/**
 * External dependencies
 */
 import { __ } from '@wordpress/i18n';
 import {
     Modal,
     __experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis --- _experimentalText unlikely to change/disappear and also used by WC Core
 } from '@wordpress/components';

 /**
  * Internal dependencies
  */
 import documentationLinkProps from '../helpers/documentation-link-props';

const tosHref = 'https://business.pinterest.com/business-terms-of-service/';
const privacyPolicyHref = 'https://policy.pinterest.com/privacy-policy';
const advertisingServicesAgreementHref = 'https://business.pinterest.com/pinterest-advertising-services-agreement/';

/**
 * Modal used for displaying terms and conditions information required for Ads Credit Feature.
 * 
 * @param {Function} props.onModalClose Action to call when the modal gets closed.
 * 
 * @fires wcadmin_pfw_documentation_link_click with `{ link_id: 'terms-of-service', context: 'ads-credits-terms-and-conditions' }`
 * @fires wcadmin_pfw_documentation_link_click with `{ link_id: 'privacy-policy', context: 'ads-credits-terms-and-conditions' }`
 * @fires wcadmin_pfw_documentation_link_click with `{ link_id: 'advertising-services-agreement', context: 'ads-credits-terms-and-conditions' }`

 * @return {JSX.Element} Rendered element.
 */
const AdsCreditsTermsAndConditionsModal = ( { onModalClose } ) => {
    return (
        <Modal
            title={
                <>{ __( 'Terms & Conditions', 'pinterest-for-woocommerce' ) }</>
            }
            onRequestClose={ onModalClose }
            className="pinterest-for-woocommerce-landing-page__credits-section__tac-modal"
        >
            <Text variant="body">
                <div className="woocommerce-setup-guide__step-modal__wrapper">
                    <p>
                        { __(
                            'To redeem the $125 ad credit from Pinterest, you would need to be a new customer to Pinterest ads, complete the setup of Pinterest for WooCommerce, and spend $15 with Pinterest ads. Credits may take up to 24 hours to be credited to the user.',
                            'pinterest-for-woocommerce'
                        ) }
                    </p>
                    <p>
                        { __(
                            'Each user is only eligible to receive the credits once. Ad credits may vary by country and is subject to availability.',
                            'pinterest-for-woocommerce'
                        ) }
                    </p>
                    <p>
                        { __(
                            'The following terms and conditions apply:',
                            'pinterest-for-woocommerce'
                        ) }
                    </p>
                    <p>
                        <a
                            { ...documentationLinkProps( {
                                href: tosHref,
                                linkId: 'terms-of-service',
                                context: 'ads-credits-terms-and-conditions',
                                rel: 'noreferrer',
                            } ) }
                        >
                            { __(
                                'Business Terms of Service',
                                'pinterest-for-woocommerce'
                            ) }
                        </a>
                    </p>
                    <p>
                        <a
                            { ...documentationLinkProps( {
                                href: privacyPolicyHref,
                                linkId: 'privacy-policy',
                                context: 'ads-credits-terms-and-conditions',
                                rel: 'noreferrer',
                            } ) }
                        >
                            { __(
                                'Privacy Policy',
                                'pinterest-for-woocommerce'
                            ) }
                        </a>
                    </p>
                    <p>
                        <a
                            { ...documentationLinkProps( {
                                href: advertisingServicesAgreementHref,
                                linkId: 'advertising-services-agreement',
                                context: 'ads-credits-terms-and-conditions',
                                rel: 'noreferrer',
                            } ) }
                        >
                            { __(
                                'Pinterest Advertising Services Agreement',
                                'pinterest-for-woocommerce'
                            ) }
                        </a>
                    </p>
                </div>
            </Text>
        </Modal>
    );
};

export default AdsCreditsTermsAndConditionsModal;
