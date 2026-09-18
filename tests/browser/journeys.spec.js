const { test, expect } = require( '@playwright/test' );
const { wp, read, seed, login, localNetwork, baseURL } = require( './helpers' );

test.beforeEach( async ( { context } ) => {
	seed();
	await localNetwork( context );
} );

test.afterEach( () => {
	expect(
		read( 'get_option("pinterest_e2e_unexpected_http", array())' )
	).toEqual( [] );
} );

test( 'merchant connection failure, retry, configuration reload and disconnect', async ( {
	page,
} ) => {
	await login(
		page,
		process.env.PINTEREST_E2E_ADMIN || 'admin',
		process.env.PINTEREST_E2E_PASSWORD || 'password'
	);

	await page.goto(
		'/wp-admin/admin.php?page=wc-admin&path=%2Fpinterest%2Fconnection'
	);
	await page.getByRole( 'link', { name: 'Connect', exact: true } ).click();
	await expect(
		page.locator( '.components-snackbar__content' ).filter( {
			hasText: 'Token data missing, please try again later.',
		} )
	).toBeVisible();
	expect( read( 'Pinterest_For_Woocommerce::is_connected()' ) ).toBe( false );
	wp( 'option', 'update', 'pinterest_e2e_connect_fail', '0' );
	await page.getByRole( 'link', { name: 'Connect', exact: true } ).click();
	await expect(
		page.getByRole( 'button', { name: 'Complete Setup', exact: true } )
	).toBeVisible();
	await page.goto(
		'/wp-admin/admin.php?page=wc-admin&path=%2Fpinterest%2Fconnection'
	);
	await expect(
		page.getByRole( 'button', { name: 'Disconnect', exact: true } )
	).toBeVisible();
	expect( read( 'Pinterest_For_Woocommerce::is_connected()' ) ).toBe( true );
	expect(
		read(
			'array(Pinterest_For_Woocommerce::get_setting("tracking_advertiser",true),Pinterest_For_Woocommerce::get_setting("tracking_tag",true))'
		)
	).toEqual( [ 'merchant-advertiser', '1234567890123' ] );
	await page.goto(
		'/wp-admin/admin.php?page=wc-admin&path=%2Fpinterest%2Fsettings'
	);
	// The settings form appears before its initial request resolves.
	await expect(
		page.getByRole( 'checkbox', {
			name: 'Add Rich Pins for Products',
			exact: true,
		} )
	).toBeChecked();
	await page
		.getByRole( 'checkbox', { name: 'Enable Debug Logging', exact: true } )
		.check();
	await page
		.getByRole( 'button', { name: 'Save changes', exact: false } )
		.click();
	await expect
		.poll( () =>
			read(
				'Pinterest_For_Woocommerce::get_setting("enable_debug_logging", true)'
			)
		)
		.toBe( true );
	await page.reload();
	await expect(
		page.getByRole( 'checkbox', {
			name: 'Enable Debug Logging',
			exact: true,
		} )
	).toBeChecked();
	await page.goto(
		'/wp-admin/admin.php?page=wc-admin&path=%2Fpinterest%2Fconnection'
	);
	await page
		.getByRole( 'button', { name: 'Disconnect', exact: true } )
		.click();
	await page
		.getByRole( 'button', { name: "Yes, I'm sure", exact: true } )
		.click();
	await expect(
		page.getByRole( 'button', { name: 'Get started', exact: true } )
	).toBeVisible();
	await page.reload();
	const state = read(
		'array(Pinterest_For_Woocommerce::is_connected(), Pinterest_For_Woocommerce::get_data("token_data",true), Pinterest_For_Woocommerce::get_setting("tracking_advertiser",true), Pinterest_For_Woocommerce::get_setting("tracking_tag",true), Pinterest_For_Woocommerce::get_setting("enable_debug_logging",true), Pinterest_For_Woocommerce::get_setting("merchant_extension_setting",true), get_option("pinterest_e2e_remote_feeds"))'
	);
	expect( state.slice( 0, 6 ) ).toEqual( [
		false,
		null,
		false,
		false,
		true,
		'retain',
	] );
	expect( state[ 6 ] ).toEqual( [
		{
			id: 'merchant-feed',
			name: 'Merchant upload',
			location: 'https://merchant.example/feed.xml',
		},
	] );
} );

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
	} ) => {
		const fixture = read( 'get_option("pinterest_e2e_fixture")' );
		const shipping = role === 'guest' && mode === 'classic' ? 7 : 0;
		if ( shipping )
			wp(
				'eval',
				`$product=wc_get_product(${ fixture.product });$product->set_virtual(false);$product->save();`
			);
		wp(
			'eval',
			`Pinterest_For_Woocommerce::save_token_data(array("access_token"=>"local-access","refresh_token"=>"local-refresh","expires_in"=>3600,"refresh_token_expires_in"=>7200,"scopes"=>"ads:read")); Pinterest_For_Woocommerce::save_settings(array("tracking_advertiser"=>"merchant-advertiser","tracking_tag"=>"1234567890123","track_conversions"=>true,"track_conversions_capi"=>true)); update_option("woocommerce_checkout_page_id",${ fixture.pages[ mode ] });`
		);
		await context.addCookies( [
			{ name: 'pinterest_e2e_consent', value: consent, url: baseURL },
		] );
		if ( role === 'customer' )
			await login(
				page,
				'pinterest_customer',
				'pinterest-test-password'
			);
		await page.goto( `/?add-to-cart=${ fixture.product }&quantity=2` );
		await page.goto(
			'/?page_id=' + read( 'get_option("woocommerce_cart_page_id")' )
		);
		// The cart shortcode gives both checkout modes the same real coupon path.
		await page
			.locator( '#coupon_code' )
			.fill( 'pinterest-browser-discount' );
		await page.getByRole( 'button', { name: 'Apply coupon' } ).click();
		await expect(
			page.getByText( 'Coupon code applied successfully.', {
				exact: false,
			} )
		).toBeVisible();
		await page.goto( `/?page_id=${ fixture.pages[ mode ] }` );
		if ( mode === 'classic' ) {
			for ( const [ key, value ] of Object.entries( {
				first_name: 'Case',
				last_name: 'Customer',
				address_1: '123 Test Street',
				city: 'San Francisco',
				postcode: '94107',
				phone: '5551234567',
				email: `${ role }@example.test`,
			} ) )
				await page.locator( `#billing_${ key }` ).fill( value );
			await page.locator( '#billing_country' ).selectOption( 'US' );
			await page.locator( '#billing_state' ).selectOption( 'CA' );
			await page.locator( '#place_order' ).click();
		} else {
			const editAddress = page.locator(
				'.wc-block-components-address-card__edit'
			);
			if ( await editAddress.isVisible() ) await editAddress.click();
			const email = page.locator( '#email' );
			if ( await email.isVisible() )
				await email.fill( `${ role }@example.test` );
			for ( const [ key, value ] of Object.entries( {
				first_name: 'Case',
				last_name: 'Customer',
				address_1: '123 Test Street',
				city: 'San Francisco',
				postcode: '94107',
			} ) )
				await page.locator( `#billing-${ key }` ).fill( value );
			await page
				.getByRole( 'button', { name: 'Place order', exact: false } )
				.click();
		}
		await expect(
			page.getByRole( 'heading', { name: 'Order received', exact: true } )
		).toBeVisible();
		// The tracking scripts are emitted in wp_footer, after the order heading.
		await page.waitForLoadState( 'load' );
		const order = read(
			'array_map(function($o){return array("id"=>$o->get_id(),"currency"=>$o->get_currency(),"total"=>$o->get_total(),"discount"=>$o->get_discount_total(),"shipping"=>$o->get_shipping_total(),"tax"=>$o->get_total_tax(),"customer"=>$o->get_customer_id(),"status"=>$o->get_status(),"items"=>array_values(array_map(function($i){return array("product"=>$i->get_product_id(),"quantity"=>$i->get_quantity(),"total"=>$i->get_total());},$o->get_items())));},wc_get_orders(array("limit"=>-1,"meta_key"=>"_pinterest_e2e","meta_value"=>"yes")))'
		);
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
		const events = read(
			'get_option("pinterest_e2e_events", array())'
		).filter( ( event ) => event.event_name === 'checkout' );
		const tag = await page.evaluate(
			() =>
				window.pintrk?.queue?.filter(
					( event ) =>
						event[ 0 ] === 'track' && event[ 1 ] === 'Checkout'
				) || []
		);
		if ( consent === 'deny' ) {
			expect(
				read( 'get_option("pinterest_e2e_events", array())' )
			).toEqual( [] );
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
