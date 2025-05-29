/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	Button,
	Flex,
	Modal,
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis --- _experimentalText unlikely to change/disappear and also used by WC Core
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useSettingsDispatch, useCreateNotice } from '../helpers/effects';
import landingConnectImage from '../../../../images/landing_connect.svg';

/**
 * Modal to encourage merchants to enable Conversions API for better tracking.
 *
 * @param {Object} options
 * @param {Function} options.onCloseModal Action to call when the modal gets closed.
 * @param {Function} options.onDismiss Action to call when the modal is dismissed (optional).
 *
 * @return {JSX.Element} rendered component
 */
const CapiEnablementModal = ( { onCloseModal, onDismiss } ) => {
	const [ isEnabling, setIsEnabling ] = useState( false );
	const setAppSettings = useSettingsDispatch( true );
	const createNotice = useCreateNotice();

	const handleEnableCapi = async () => {
		setIsEnabling( true );
		try {
			await setAppSettings( {
				track_conversions_capi: true,
			} );

			createNotice(
				'success',
				__(
					'Conversions API has been enabled successfully.',
					'pinterest-for-woocommerce'
				)
			);

			onCloseModal();
		} catch ( error ) {
			createNotice(
				'error',
				__(
					'There was a problem enabling Conversions API.',
					'pinterest-for-woocommerce'
				)
			);
		} finally {
			setIsEnabling( false );
		}
	};

	const handleNotNow = () => {
		// Call the dismiss callback to mark modal as dismissed
		if ( onDismiss ) {
			onDismiss();
		}
		onCloseModal();
	};

	return (
		<Modal
			title={ __(
				'Improve your conversion tracking',
				'pinterest-for-woocommerce'
			) }
			onRequestClose={ onCloseModal }
			className="pinterest-for-woocommerce-capi-enablement-modal"
			style={ { maxWidth: '600px' } }
		>
			<div
				style={ {
					textAlign: 'center',
					marginBottom: '20px',
				} }
			>
				<img
					src={ landingConnectImage }
					alt={ __(
						'Pinterest connection illustration',
						'pinterest-for-woocommerce'
					) }
					style={ {
						maxWidth: '200px',
						height: 'auto',
					} }
				/>
			</div>

			<Text variant="body.large">
				{ __(
					'Enable Pinterest Conversions API for more reliable conversion tracking. This helps you better measure ad campaign success, optimize your marketing strategies, and understand customer behavior. Benefits:',
					'pinterest-for-woocommerce'
				) }
			</Text>

			<ul
				style={ {
					marginLeft: '20px',
					marginTop: '16px',
					marginBottom: '24px',
					listStyleType: 'disc',
				} }
			>
				<li>
					{ __(
						'More complete conversion data',
						'pinterest-for-woocommerce'
					) }
				</li>
				<li>
					{ __(
						'Better campaign optimization',
						'pinterest-for-woocommerce'
					) }
				</li>
				<li>
					{ __(
						'Improved audience targeting',
						'pinterest-for-woocommerce'
					) }
				</li>
				<li>
					{ __(
						'Reduced impact from browser limitations',
						'pinterest-for-woocommerce'
					) }
				</li>
			</ul>

			<Flex direction="row" justify="flex-end" gap={ 2 }>
				<Button
					isTertiary
					onClick={ handleNotNow }
					disabled={ isEnabling }
				>
					{ __( 'Not now', 'pinterest-for-woocommerce' ) }
				</Button>
				<Button
					isPrimary
					onClick={ handleEnableCapi }
					isBusy={ isEnabling }
					disabled={ isEnabling }
				>
					{ isEnabling
						? __( 'Enabling…', 'pinterest-for-woocommerce' )
						: __(
								'Enable Conversions API',
								'pinterest-for-woocommerce'
						  ) }
				</Button>
			</Flex>
		</Modal>
	);
};

export default CapiEnablementModal;
