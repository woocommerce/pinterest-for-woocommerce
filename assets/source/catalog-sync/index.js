/**
 * External dependencies
 */
import { render } from '@wordpress/element';

/**
 * Internal dependencies
 */
import CatalogSyncApp from './app/App';
import './app/style.scss';

const appRoot = document.getElementById( 'pin4wc-catalog-sync' );

if ( appRoot ) {
	render( <CatalogSyncApp />, appRoot );
}
