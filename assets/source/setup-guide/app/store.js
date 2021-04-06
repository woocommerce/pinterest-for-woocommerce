import apiFetch from '@wordpress/api-fetch';
import { registerStore } from '@wordpress/data';

/**
 * Selectors
 */
const selectors = {
    getOption( state, name ) {
		return state[ name ];
	}
};

/**
 * Resolvers
 */
const resolvers = {
    *getOption( name ) {
        const result = yield batchFetch( name );
        yield actions.receiveOptions( result );
    },
};

/**
 * Actions
 */
const actions = {
	receiveOptions( options ) {
		return {
			type: 'RECEIVE_OPTIONS',
			options,
		};
	},
	setIsUpdating( isUpdating ) {
		return {
			type: 'SET_IS_UPDATING',
			isUpdating,
		};
	},
	*updateOptions( data ) {
		yield actions.setIsUpdating( true );
		yield actions.receiveOptions( data );

		const results = yield apiFetch( {
			path: pin4wcSetupGuide.apiRoute + '/options',
			method: 'POST',
			data,
		} ).then( ( response ) => {
			return { success: true, ...response }
		} ).catch( () => {
			return { success: false }
		} );

		actions.setIsUpdating( false );
		return results;
	}
};

/**
 * Controls
 */
let optionNames = [];
const fetches = {};

const batchFetch = ( optionName ) => {
	return {
		type: 'BATCH_FETCH',
		optionName,
	};
};

const controls = {
    BATCH_FETCH( { optionName } ) {
		optionNames.push( optionName );

		return new Promise( ( resolve ) => {
			setTimeout( function () {
				const names = optionNames.join( ',' );
				if ( fetches[ names ] ) {
					return fetches[ names ].then( ( result ) => {
						resolve( result[ optionName ] );
					} );
				}

				const url = pin4wcSetupGuide.apiRoute + '/options?options=' + names;
				fetches[ names ] = apiFetch( { path: url } );
				fetches[ names ].then( ( result ) => resolve( result ) );

				setTimeout( () => {
					optionNames = [];
					delete fetches[ names ];
				}, 1 );
			}, 1 );
		} );
	},
};

/**
 * Reducer
 *
 * @param {object} state
 * @param {string} action
 */
function reducer(
	state = { isUpdating: false, requestingErrors: {} },
	{ type, options, error, isUpdating, name }
) {
    switch ( type ) {
		case 'RECEIVE_OPTIONS':
			state = {
				...state,
				...options,
			};
			break;
		case 'SET_IS_UPDATING':
			state = {
				...state,
				isUpdating,
			};
			break;
	}
	return state;
};

/**
 * Register Store
 */
const store = registerStore(
    'pinterest/data',
    {
        actions,
        controls,
        reducer,
        resolvers,
        selectors,
    }
);

export default store;
