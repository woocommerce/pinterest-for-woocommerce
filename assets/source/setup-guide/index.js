/**
 * External dependencies
 */
import { render } from '@wordpress/element';

/**
 * Internal dependencies
 */
import Router from './app/Router';

const appRoot = document.getElementById( 'pin4wc-setup-guide' );

if ( appRoot ) {
	render( <Router />, appRoot );
}
