/**
 * External dependencies
 */
import { useState } from '@wordpress/element';
import { sprintf, __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import apiFetch from '@wordpress/api-fetch';
import { Spinner } from '@woocommerce/components';
import { getNewPath } from '@woocommerce/navigation';
import {
	Button,
	CardBody,
	Flex,
	FlexItem,
	FlexBlock,
	Modal,
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis --- _experimentalText unlikely to change/disappear and also used by WC Core
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useCreateNotice } from '../../helpers/effects';

const AccountConnection = ( { isConnected, setIsConnected, accountData } ) => {
	const createNotice = useCreateNotice();

	const [ isConfirmationModalOpen, setIsConfirmationModalOpen ] = useState(
		false
	);

	const openConfirmationModal = () => {
		setIsConfirmationModalOpen( true );
	};

	const closeConfirmationModal = () => {
		setIsConfirmationModalOpen( false );
	};

	const renderConfirmationModal = () => {
		return (
			<Modal
				title={
					<>{ __( 'Are you sure?', 'pinterest-for-woocommerce' ) }</>
				}
				onRequestClose={ closeConfirmationModal }
				className="woocommerce-setup-guide__step-modal"
			>
				<div className="woocommerce-setup-guide__step-modal__wrapper">
					<p>
						{ __(
							'Are you sure you want to disconnect this account?',
							'pinterest-for-woocommerce'
						) }
					</p>
					<div className="woocommerce-setup-guide__step-modal__buttons">
						<Button
							isDestructive
							isSecondary
							onClick={ handleDisconnectAccount }
						>
							{ __(
								"Yes, I'm sure",
								'pinterest-for-woocommerce'
							) }
						</Button>
						<Button isTertiary onClick={ closeConfirmationModal }>
							{ __( 'Cancel', 'pinterest-for-woocommerce' ) }
						</Button>
					</div>
				</div>
			</Modal>
		);
	};

	const handleDisconnectAccount = async () => {
		closeConfirmationModal();

		const result = await apiFetch( {
			path:
				wcSettings.pinterest_for_woocommerce.apiRoute +
				'/auth_disconnect',
			method: 'POST',
		} );

		if ( ! result.disconnected ) {
			createNotice(
				'error',
				__(
					'There was a problem while trying to disconnect.',
					'pinterest-for-woocommerce'
				)
			);
		} else {
			setIsConnected( false );

			// Force reload WC admin page to initiate the relevant dependencies of the Dashboard page.
			const path = getNewPath( {}, '/pinterest/landing', {} );

			window.location = new URL(
				decodeEntities( wcSettings.adminUrl + path )
			);
		}
	};

	return (
		<CardBody size="large">
			{ isConnected === true ? ( // eslint-disable-line no-nested-ternary --- Code is reasonable readable
				<Flex>
					<FlexBlock className="is-connected">
						<Text variant="subtitle">
							{ __(
								'Pinterest Account',
								'pinterest-for-woocommerce'
							) }
						</Text>
						{ accountData?.id && (
							<Text variant="body">
								{ sprintf(
									'%1$s: %2$s - %3$s',
									__(
										'Account',
										'pinterest-for-woocommerce'
									),
									accountData.username,
									accountData.id
								) }
							</Text>
						) }
						<Button
							isLink
							isDestructive
							onClick={ openConfirmationModal }
						>
							{ __(
								'Disconnect Pinterest Account',
								'pinterest-for-woocommerce'
							) }
						</Button>
					</FlexBlock>
				</Flex>
			) : isConnected === false ? (
				<Flex>
					<FlexBlock>
						<Text variant="subtitle">
							{ __(
								'Connect your Pinterest Account',
								'pinterest-for-woocommerce'
							) }
						</Text>
					</FlexBlock>
					<FlexItem>
						<Button
							isSecondary
							href={ decodeEntities(
								wcSettings.pinterest_for_woocommerce
									.serviceLoginUrl
							) }
						>
							{ __( 'Connect', 'pinterest-for-woocommerce' ) }
						</Button>
					</FlexItem>
				</Flex>
			) : (
				<Spinner />
			) }

			{ isConfirmationModalOpen && renderConfirmationModal() }
		</CardBody>
	);
};

export default AccountConnection;
