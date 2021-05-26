/**
 * External dependencies
 */
import { controls as dataControls } from '@wordpress/data-controls';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { WC_ADMIN_NAMESPACE } from './constants';

let settingNames = [];
const fetches = {};

export const batchFetch = ( settingName ) => {
	return {
		type: 'BATCH_FETCH',
		settingName,
	};
};

export const controls = {
	...dataControls,
	BATCH_FETCH( { settingName } ) {
		settingNames.push( settingName );

		return new Promise( ( resolve ) => {
			setTimeout( function () {
				const names = settingNames.join( ',' );
				if ( fetches[ names ] ) {
					return fetches[ names ].then( ( result ) => {
						resolve( result[ settingName ] );
					} );
				}

				const url = WC_ADMIN_NAMESPACE + '/options?options=' + names;
				fetches[ names ] = apiFetch( { path: url } );
				fetches[ names ].then( ( result ) => resolve( result ) );

				// Clear setting names after all resolved;
				setTimeout( () => {
					settingNames = [];
					// Delete the fetch after to allow wp data to handle cache invalidation.
					delete fetches[ names ];
				}, 1 );
			}, 1 );
		} );
	},
};
