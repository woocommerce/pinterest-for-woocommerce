/**
 * External dependencies
 */
import '@wordpress/notices';
import { compose } from '@wordpress/compose';
import { withSelect, withDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { OPTIONS_STORE_NAME } from '@woocommerce/data';

/**
 * Internal dependencies
 */
import SetupAccount from '../steps/SetupAccount';
import VerifyDomain from '../steps/VerifyDomain';
import ConfigureSettings from '../steps/ConfigureSettings';
import TransientNotices from '../transient-notices';

const SettingsApp = ( { pin4wc, createNotice } ) => {
	useEffect( () => {
		document.body.classList.add( 'woocommerce-setup-guide__body' );
		document.body.classList.add( 'woocommerce-setup-guide--wizard' );

		if ( pin4wcSetupGuide.error ) {
			createNotice( 'error', pin4wcSetupGuide.error );
		}

		return () => {
			document.body.classList.remove( 'woocommerce-setup-guide--wizard' );
			document.body.classList.remove( 'woocommerce-setup-guide__body' );
		};
	}, [ createNotice ] );

	const isConnected = () => {
		return undefined === pin4wc
			? undefined
			: !! pin4wc?.token?.access_token;
	};

	const isDomainVerified = () => {
		return undefined === pin4wc
			? undefined
			: pin4wcSetupGuide.domainToVerify in
					pin4wc?.account_data?.verified_domains;
	};

	return (
		<div className="woocommerce-layout">
			<div className="woocommerce-layout__main">
				<TransientNotices />
				<div className="woocommerce-setup-guide__container">
					<SetupAccount view="settings" />
					{ isConnected() && <VerifyDomain view="settings" /> }
					{ isConnected() && isDomainVerified() && (
						<ConfigureSettings view="settings" />
					) }
				</div>
			</div>
		</div>
	);
};

export default compose(
	withSelect( ( select ) => {
		const { getOption } = select( OPTIONS_STORE_NAME );

		return {
			pin4wc: getOption( pin4wcSetupGuide.optionsName ),
		};
	} ),
	withDispatch( ( dispatch ) => {
		const { createNotice } = dispatch( 'core/notices' );

		return {
			createNotice,
		};
	} )
)( SettingsApp );
