/**
 * External dependencies
 */
import { render } from '@wordpress/element';

/**
 * Internal dependencies
 */
import App from './app/App';

const appRoot = document.getElementById( 'pin4wc-setup-guide-app' );

if ( appRoot ) {
	render( <App />, appRoot );
}
