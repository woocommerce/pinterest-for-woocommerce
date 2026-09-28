const { expect } = require( '@playwright/test' );

async function login( page, username, password ) {
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( username );
	await page.locator( '#user_pass' ).fill( password );
	await page.locator( '#wp-submit' ).click();
	await page.goto( '/my-account/' );
	await expect(
		page.locator(
			'.woocommerce-MyAccount-navigation-link--customer-logout'
		)
	).toBeVisible();
}

module.exports = {
	asAdmin: ( page ) =>
		login(
			page,
			process.env.PINTEREST_E2E_ADMIN || 'admin',
			process.env.PINTEREST_E2E_PASSWORD || 'password'
		),
	asCustomer: ( page ) =>
		login( page, 'pinterest_customer', 'pinterest-test-password' ),
};
