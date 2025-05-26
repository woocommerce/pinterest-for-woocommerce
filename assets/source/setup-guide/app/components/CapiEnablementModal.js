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
import {
	useSettingsDispatch,
	useCreateNotice,
} from '../helpers/effects';

/**
 * Modal to encourage merchants to enable Conversions API for better tracking.
 *
 * @param {Object} options
 * @param {Function} options.onCloseModal Action to call when the modal gets closed.
 *
 * @return {JSX.Element} rendered component
 */
const CapiEnablementModal = ( { onCloseModal } ) => {
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

	return (
		<Modal
			title={ __(
				'Improve your conversion tracking',
				'pinterest-for-woocommerce'
			) }
			onRequestClose={ onCloseModal }
			className="pinterest-for-woocommerce-capi-enablement-modal"
		>
			<Text variant="body.large">
				{ __(
					'Enable Pinterest Conversions API for more reliable and accurate conversion tracking. This server-side tracking works alongside your Pinterest Tag to capture conversions that might be missed due to browser restrictions or ad blockers.',
					'pinterest-for-woocommerce'
				) }
			</Text>
			
			<Text variant="body" style={{ marginTop: '16px', marginBottom: '8px' }}>
				{ __(
					'Benefits of enabling Conversions API:',
					'pinterest-for-woocommerce'
				) }
			</Text>
			
			<ul style={{ marginLeft: '20px', marginBottom: '24px' }}>
				<li>{ __( 'More complete conversion data', 'pinterest-for-woocommerce' ) }</li>
				<li>{ __( 'Better campaign optimization', 'pinterest-for-woocommerce' ) }</li>
				<li>{ __( 'Improved audience targeting', 'pinterest-for-woocommerce' ) }</li>
				<li>{ __( 'Reduced impact from browser limitations', 'pinterest-for-woocommerce' ) }</li>
			</ul>

			<Flex direction="row" justify="flex-end" gap={ 2 }>
				<Button
					variant="tertiary"
					onClick={ onCloseModal }
					disabled={ isEnabling }
				>
					{ __( 'Not now', 'pinterest-for-woocommerce' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ handleEnableCapi }
					isBusy={ isEnabling }
					disabled={ isEnabling }
				>
					{ isEnabling
						? __( 'Enabling...', 'pinterest-for-woocommerce' )
						: __( 'Enable Conversions API', 'pinterest-for-woocommerce' )
					}
				</Button>
			</Flex>
		</Modal>
	);
};

export default CapiEnablementModal;