const { test, expect } = require( '../fixtures' );

test( 'merchant connection failure, retry, configuration reload and disconnect', async ( {
	adminPage: page,
	store,
} ) => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-admin&path=%2Fpinterest%2Fconnection'
	);
	await page.getByRole( 'link', { name: 'Connect', exact: true } ).click();
	await expect(
		page.locator( '.components-snackbar__content' ).filter( {
			hasText: 'Token data missing, please try again later.',
		} )
	).toBeVisible();
	expect( ( await store.connectionState() ).connected ).toBe( false );
	await store.allowConnection();
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
	const connected = await store.connectionState();
	expect( connected ).toMatchObject( {
		connected: true,
		advertiser: 'merchant-advertiser',
		tag: '1234567890123',
		integration: {
			connected_merchant_id: 'merchant-123',
			connected_advertiser_id: 'merchant-advertiser',
			connected_tag_id: '1234567890123',
		},
	} );
	await page.goto(
		'/wp-admin/admin.php?page=wc-admin&path=%2Fpinterest%2Fsettings'
	);
	// Confirm the persisted settings are available before editing the configuration.
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
		.poll( async () => ( await store.connectionState() ).debugLogging )
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
	const state = await store.connectionState();
	expect( state ).toMatchObject( {
		connected: false,
		token: null,
		advertiser: false,
		tag: false,
		debugLogging: true,
		merchantExtensionSetting: 'retain',
	} );
	expect( state.remoteFeeds ).toEqual( [
		{
			id: 'merchant-feed',
			name: 'Merchant upload',
			location: 'https://merchant.example/feed.xml',
		},
	] );
} );
