const { test, expect } = require( '../fixtures' );
const login = require( '../utils/login' );

for ( const [ role, mode, consent ] of [
	[ 'guest', 'classic', 'allow' ],
	[ 'guest', 'classic', 'deny' ],
	[ 'customer', 'classic', 'allow' ],
	[ 'guest', 'block', 'allow' ],
	[ 'customer', 'block', 'allow' ],
	[ 'customer', 'block', 'deny' ],
	[ 'guest', 'block', 'deny' ],
	[ 'customer', 'classic', 'deny' ],
] ) {
	test( `${ role } ${ mode } checkout with ${ consent } consent matches saved order`, async ( {
		page,
		context,
		store,
		checkout,
		baseURL,
	} ) => {
		const fixture = store.fixture;
		const shipping = role === 'guest' && mode === 'classic' ? 7 : 0;
		await store.prepareCheckout( { mode, shipping: Boolean( shipping ) } );
		await context.addCookies( [
			{ name: 'pinterest_e2e_consent', value: consent, url: baseURL },
		] );
		if ( role === 'customer' ) await login.asCustomer( page );
		await checkout.purchase( fixture, { role, mode } );
		const order = await store.orders();
		expect( order ).toHaveLength( 1 );
		expect( order[ 0 ] ).toMatchObject( {
			currency: 'EUR',
			total: shipping ? '49.90' : '42.90',
			discount: '9',
			shipping: String( shipping ),
			tax: '3.9',
			customer: role === 'guest' ? 0 : fixture.customer,
			status: 'processing',
			items: [ { product: fixture.product, quantity: 2, total: '39' } ],
		} );
		const recordedEvents = await store.events();
		const events = recordedEvents.filter(
			( event ) => event.event_name === 'checkout'
		);
		const tag = await page.evaluate(
			() =>
				window.pintrk?.queue?.filter(
					( event ) =>
						event[ 0 ] === 'track' && event[ 1 ] === 'Checkout'
				) || []
		);
		if ( consent === 'deny' ) {
			expect( recordedEvents ).toEqual( [] );
			expect( await page.evaluate( () => typeof window.pintrk ) ).toBe(
				'undefined'
			);
			expect( tag ).toEqual( [] );
		} else {
			expect( events ).toHaveLength( 1 );
			expect( events[ 0 ].custom_data ).toEqual( {
				order_id: String( order[ 0 ].id ),
				currency: 'EUR',
				value: '39.00',
				content_ids: [ String( fixture.product ) ],
				contents: [
					{
						id: String( fixture.product ),
						item_price: '19.5',
						quantity: 2,
					},
				],
				num_items: 2,
			} );
			expect( tag ).toHaveLength( 1 );
			expect( tag[ 0 ][ 2 ] ).toMatchObject( {
				order_id: String( order[ 0 ].id ),
				currency: order[ 0 ].currency,
				order_quantity: 2,
				line_items: [
					{
						product_id: fixture.product,
						product_name: 'Pinterest browser product',
						product_quantity: 2,
						product_price: 19.5,
					},
				],
			} );
			expect( Number( tag[ 0 ][ 2 ].value ) ).toBe(
				Number( order[ 0 ].items[ 0 ].total )
			);
		}
	} );
}
