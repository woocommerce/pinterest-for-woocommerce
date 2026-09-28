const { test: base, expect } = require( '@playwright/test' );
const Store = require( '../utils/store' );
const login = require( '../utils/login' );
const CheckoutPage = require( '../pages/checkout' );

const test = base.extend( {
	store: [
		async ( {}, provide ) => {
			const store = new Store();
			try {
				await store.seed();
				await provide( store );
			} finally {
				try {
					expect( await store.unexpectedHttp() ).toEqual( [] );
				} finally {
					await store.cleanup();
				}
			}
		},
		{ auto: true },
	],
	context: async ( { context, baseURL }, provide ) => {
		await context.route( '**/*', async ( route ) => {
			const url = new URL( route.request().url() );
			if ( url.origin === new URL( baseURL ).origin )
				return route.continue();
			// Inspect queued tag calls without loading Pinterest's external script.
			if ( url.hostname === 's.pinimg.com' )
				return route.fulfill( {
					contentType: 'application/javascript',
					body: '',
				} );
			return route.abort();
		} );
		await provide( context );
	},
	adminPage: async ( { page }, provide ) => {
		await login.asAdmin( page );
		await provide( page );
	},
	checkout: async ( { page }, provide ) =>
		provide( new CheckoutPage( page ) ),
} );

module.exports = { test, expect };
