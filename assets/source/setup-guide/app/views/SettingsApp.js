/**
 * External dependencies
 */
import '@wordpress/notices';
import { __ } from '@wordpress/i18n';
import { compose } from '@wordpress/compose';
import { withDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

 /**
  * Internal dependencies
  */
import SetupAccount from '../steps/SetupAccount';
import VerifyDomain from '../steps/VerifyDomain';
import ConfigureSettings from '../steps/ConfigureSettings';
import TransientNotices from '../transient-notices';

const SettingsApp = ( { createNotice } ) => {

	useEffect(() => {
		document.body.classList.add( 'woocommerce-setup-guide__body' );
		document.body.classList.add( 'woocommerce-setup-guide--wizard' );

		if ( pin4wcSetupGuide.error ) {
			createNotice(
				'error',
				pin4wcSetupGuide.error
			);
		}

		return () => {
			document.body.classList.remove( 'woocommerce-setup-guide--wizard' );
			document.body.classList.remove( 'woocommerce-setup-guide__body' );
		}
	}, [])

	return (
		<div className="woocommerce-layout">
			<div className="woocommerce-layout__main">
				<TransientNotices />
				<div className="woocommerce-setup-guide__container">
					<SetupAccount view="settings" />
					<VerifyDomain view="settings" />
					<ConfigureSettings view="settings" />
				</div>
			</div>
		</div>
	)
 }

 export default compose(
	withDispatch( dispatch => {
		const { createNotice } = dispatch( 'core/notices' );

		return {
			createNotice,
		};
	})
 )(SettingsApp);
