const { test, expect } = require( '../fixtures' );

/** Replay one native WordPress response as a cached storefront document. */
async function cachedProduct( context, store, baseURL, options = {} ) {
	const {
		phpConsent = 'allow',
		delayPageVisit = false,
		...fixtureOptions
	} = options;
	await store.prepareCheckout( { mode: 'classic', ...fixtureOptions } );
	await context.addCookies( [
		{ name: 'pinterest_e2e_consent', value: phpConsent, url: baseURL },
	] );
	const response = await context.request.get(
		`${ baseURL }/?post_type=product&p=${ store.fixture.product }`
	);
	expect( response.ok() ).toBe( true );
	let html = await response.text();
	if ( delayPageVisit ) {
		html = html.replace( /<script\b[^>]*>[\s\S]*?<\/script>/g, ( tag ) =>
			tag.includes( 'pinterest_for_woocommerce_page_visit' )
				? tag.replace(
						'<script',
						'<script type="text/plain" data-delayed-page-visit'
					)
				: tag
		);
	}
	expect( html ).toContain( 'pinterest_for_woocommerce_page_visit' );
	const url = response.url();
	await context.route( url, ( route ) =>
		route.fulfill( { contentType: 'text/html', body: html } )
	);
	return url;
}

/** Observe browser boundaries while forwarding accepted requests to WordPress. */
async function observe(
	page,
	{ consent = 'allow', fallback = false, uuid = true } = {}
) {
	await page.addInitScript(
		( options ) => {
			if ( options.consent !== 'missing' ) {
				window.wp_has_consent = ( category ) =>
					category === 'marketing' && options.consent === 'allow';
			}
			const visit =
				Number( sessionStorage.getItem( 'cachedVisit' ) || 0 ) + 1;
			sessionStorage.setItem( 'cachedVisit', String( visit ) );
			Object.defineProperty( window.crypto, 'randomUUID', {
				value: options.uuid
					? () =>
							`10000000-0000-4000-8000-${ String( visit ).padStart( 12, '0' ) }`
					: undefined,
			} );
			Date.now = () => 1700000000000 + visit;
			Math.random = () => 0.125;
			window.pageVisitTransports = [];
			const beacon = navigator.sendBeacon.bind( navigator );
			navigator.sendBeacon = ( url, body ) => {
				window.pageVisitTransports.push( {
					kind: 'beacon',
					url,
					body: Object.fromEntries( body ),
				} );
				return options.fallback ? false : beacon( url, body );
			};
			const fetch = window.fetch.bind( window );
			window.fetch = ( url, args ) => {
				if ( args?.body instanceof FormData ) {
					window.pageVisitTransports.push( {
						kind: 'fetch',
						url,
						body: Object.fromEntries( args.body ),
						method: args.method,
						credentials: args.credentials,
						keepalive: args.keepalive,
					} );
				}
				return fetch( url, args );
			};
		},
		{ consent, fallback, uuid }
	);
}

async function pageVisit( page ) {
	return page.evaluate( () => ( {
		tag:
			window.pintrk?.queue?.filter(
				( event ) =>
					event[ 0 ] === 'track' && event[ 1 ] === 'PageVisit'
			) || [],
		transport: window.pageVisitTransports,
	} ) );
}

for ( const uuid of [ true, false ] ) {
	test( `cached PageVisit creates fresh shared IDs with UUID ${ uuid }`, async ( {
		page,
		context,
		store,
		baseURL,
	} ) => {
		const url = await cachedProduct( context, store, baseURL );
		await observe( page, { uuid } );
		const ids = uuid
			? [
					'page_10000000-0000-4000-8000-000000000001',
					'page_10000000-0000-4000-8000-000000000002',
				]
			: [ 'page_loyw3v29_4i', 'page_loyw3v2a_4i' ];
		for ( const [ index, id ] of ids.entries() ) {
			if ( index ) await page.reload();
			else await page.goto( url );
			const observed = await pageVisit( page );
			expect( observed.tag ).toHaveLength( 1 );
			expect( observed.tag[ 0 ][ 2 ].event_id ).toBe( id );
			expect( observed.transport ).toEqual( [
				{
					kind: 'beacon',
					url: `${ baseURL }/wp-admin/admin-ajax.php`,
					body: {
						action: 'pinterest_for_woocommerce_page_visit',
						event_id: id,
						event_source_url: url,
					},
				},
			] );
			await expect
				.poll( async () =>
					( await store.events() ).map( ( event ) => event.event_id )
				)
				.toEqual( ids.slice( 0, index + 1 ) );
			expect(
				( await store.events() ).map( ( event ) => ( {
					event_name: event.event_name,
					event_id: event.event_id,
					event_source_url: event.event_source_url,
				} ) )
			).toEqual(
				ids.slice( 0, index + 1 ).map( ( event_id ) => ( {
					event_name: 'page_visit',
					event_id,
					event_source_url: url,
				} ) )
			);
		}
	} );
}

test( 'a declined sendBeacon forwards the same event once through fetch', async ( {
	page,
	context,
	store,
	baseURL,
} ) => {
	const url = await cachedProduct( context, store, baseURL );
	await observe( page, { fallback: true } );
	await page.goto( url );
	const observed = await pageVisit( page );
	expect( observed.transport ).toHaveLength( 2 );
	expect( observed.transport[ 1 ] ).toEqual( {
		...observed.transport[ 0 ],
		kind: 'fetch',
		method: 'POST',
		credentials: 'same-origin',
		keepalive: true,
	} );
	expect( observed.tag ).toHaveLength( 1 );
	expect( observed.transport[ 1 ].body.event_id ).toBe(
		observed.tag[ 0 ][ 2 ].event_id
	);
	await expect.poll( () => store.events() ).toHaveLength( 1 );
	expect( ( await store.events() )[ 0 ].event_id ).toBe(
		observed.tag[ 0 ][ 2 ].event_id
	);
} );

for ( const consent of [ 'deny', 'missing' ] ) {
	test( `cached PageVisit rejects ${ consent } browser consent`, async ( {
		page,
		context,
		store,
		baseURL,
	} ) => {
		const url = await cachedProduct( context, store, baseURL );
		await observe( page, { consent } );
		await page.goto( url );
		expect( await pageVisit( page ) ).toEqual( { tag: [], transport: [] } );
		expect( await store.events() ).toEqual( [] );
	} );
}

test( 'the native beacon handler rejects a cached crawler visit', async ( {
	page,
	context,
	store,
	baseURL,
} ) => {
	const url = await cachedProduct( context, store, baseURL );
	await observe( page );
	await context.setExtraHTTPHeaders( {
		'User-Agent': 'Googlebot/2.1 (+http://www.google.com/bot.html)',
	} );
	const responsePromise = page.waitForResponse( ( result ) =>
		result.url().includes( '/wp-admin/admin-ajax.php' )
	);
	await page.goto( url );
	const response = await responsePromise;
	expect( response.status() ).toBe( 200 );
	expect( ( await pageVisit( page ) ).transport ).toHaveLength( 1 );
	expect( await store.events() ).toEqual( [] );
} );

for ( const consent of [ 'allow', 'deny', 'missing' ] ) {
	test( `cached standalone CAPI beacon respects ${ consent } consent`, async ( {
		page,
		context,
		store,
		baseURL,
	} ) => {
		const url = await cachedProduct( context, store, baseURL, {
			without_tag: true,
		} );
		await observe( page, { consent } );
		await page.goto( url );
		const observed = await pageVisit( page );
		expect( observed.tag ).toEqual( [] );
		if ( consent === 'allow' ) {
			expect( observed.transport ).toHaveLength( 1 );
			await expect.poll( () => store.events() ).toHaveLength( 1 );
			expect( ( await store.events() )[ 0 ].event_id ).toBe(
				observed.transport[ 0 ].body.event_id
			);
		} else {
			expect( observed.transport ).toEqual( [] );
			expect( await store.events() ).toEqual( [] );
		}
	} );
}

test( 'the consent API loaded in the footer permits the cached PageVisit', async ( {
	page,
	context,
	store,
	baseURL,
} ) => {
	await context.addCookies( [
		{ name: 'pinterest_e2e_late_consent', value: 'allow', url: baseURL },
	] );
	const url = await cachedProduct( context, store, baseURL );
	await observe( page, { consent: 'missing' } );
	await page.goto( url );
	const observed = await pageVisit( page );
	expect( observed.tag ).toHaveLength( 1 );
	expect( observed.transport ).toHaveLength( 1 );
	await expect.poll( () => store.events() ).toHaveLength( 1 );
	expect( ( await store.events() )[ 0 ].event_id ).toBe(
		observed.tag[ 0 ][ 2 ].event_id
	);
} );

test( 'delayed emitted code waits for a consent API loaded after document readiness', async ( {
	page,
	context,
	store,
	baseURL,
} ) => {
	const url = await cachedProduct( context, store, baseURL, {
		delayPageVisit: true,
	} );
	await observe( page, { consent: 'missing' } );
	await page.goto( url );
	expect( await page.evaluate( () => document.readyState ) ).toBe(
		'complete'
	);
	const script = page.locator( 'script[data-delayed-page-visit]' );
	await expect( script ).toHaveCount( 1 );
	await page.addScriptTag( { content: await script.textContent() } );
	expect( await pageVisit( page ) ).toEqual( { tag: [], transport: [] } );
	await page.evaluate( () => {
		window.wp_has_consent = () => true;
	} );
	await expect
		.poll( async () => ( await pageVisit( page ) ).transport.length )
		.toBe( 1 );
	await expect.poll( () => store.events() ).toHaveLength( 1 );
	expect( ( await store.events() )[ 0 ].event_id ).toBe(
		( await pageVisit( page ) ).tag[ 0 ][ 2 ].event_id
	);
} );

for ( const profile of [ 'global', 'user-consent', 'api' ] ) {
	test( `custom PHP ${ profile } tracking override keeps its existing behavior`, async ( {
		page,
		context,
		store,
		baseURL,
	} ) => {
		await context.addCookies( [
			{
				name: 'pinterest_e2e_force_tracking',
				value: profile,
				url: baseURL,
			},
		] );
		const url = await cachedProduct( context, store, baseURL, {
			phpConsent: 'deny',
		} );
		await observe( page, { consent: 'deny' } );
		await page.goto( url );
		expect( ( await pageVisit( page ) ).tag ).toHaveLength( 1 );
		expect( ( await pageVisit( page ) ).transport ).toHaveLength( 1 );
		await expect.poll( () => store.events() ).toHaveLength( 1 );
	} );
}

for ( const consent of [ true, false ] ) {
	test( `geo-IP pending consent blocks transport until type is defined with consent ${ consent }`, async ( {
		page,
		context,
		store,
		baseURL,
	} ) => {
		const url = await cachedProduct( context, store, baseURL );
		await observe( page );
		await page.addInitScript( () => {
			window.waitfor_consent_hook = true;
		} );
		await page.goto( url );
		expect( await pageVisit( page ) ).toEqual( { tag: [], transport: [] } );
		expect( await store.events() ).toEqual( [] );
		await page.evaluate( ( allowed ) => {
			window.wp_consent_type = 'optin';
			window.wp_has_consent = ( category ) =>
				category === 'marketing' && allowed;
			document.dispatchEvent( new Event( 'wp_consent_type_defined' ) );
		}, consent );
		if ( consent ) {
			await expect.poll( () => store.events() ).toHaveLength( 1 );
			expect( ( await pageVisit( page ) ).transport ).toHaveLength( 1 );
		} else {
			expect( await pageVisit( page ) ).toEqual( {
				tag: [],
				transport: [],
			} );
			expect( await store.events() ).toEqual( [] );
		}
	} );
}
