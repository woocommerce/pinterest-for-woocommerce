/**
 * External dependencies
 */
import { render } from '@wordpress/element';

/**
 * Internal dependencies
 */
import SettingsApp from './app/views/SettingsApp';
import './app/style.scss';

const appRoot = document.getElementById( 'pin4wc-setup-guide' );

if ( appRoot ) {
	render( <SettingsApp />, appRoot );
}
