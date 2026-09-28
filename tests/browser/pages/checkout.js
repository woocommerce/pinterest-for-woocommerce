const { expect } = require( '@playwright/test' );
const {
	fillBillingCheckoutBlocks,
} = require( '@woocommerce/e2e-utils-playwright' );

class CheckoutPage {
	constructor( page ) {
		this.page = page;
	}

	async purchase( { product, cart, pages }, { role, mode } ) {
		const page = this.page;
		const email = `${ role }@example.test`;
		const billing = {
			country: 'US',
			firstName: 'Case',
			lastName: 'Customer',
			address: '123 Test Street',
			city: 'San Francisco',
			state: 'CA',
			zip: '94107',
		};

		// The shared cart helper requires a Store API cart; this suite uses the shortcode cart.
		await page.goto( `/?add-to-cart=${ product }&quantity=2` );
		await page.goto( `/?page_id=${ cart }` );
		await page
			.locator( '#coupon_code' )
			.fill( 'pinterest-browser-discount' );
		await page.getByRole( 'button', { name: 'Apply coupon' } ).click();
		await expect(
			page.getByText( 'Coupon code applied successfully.', {
				exact: false,
			} )
		).toBeVisible();
		await page.goto( `/?page_id=${ pages[ mode ] }` );

		if ( mode === 'classic' ) {
			await page
				.locator( '#billing_country' )
				.selectOption( billing.country );
			await page
				.locator( '#billing_state' )
				.selectOption( billing.state );
			for ( const [ key, value ] of Object.entries( {
				first_name: billing.firstName,
				last_name: billing.lastName,
				address_1: billing.address,
				city: billing.city,
				postcode: billing.zip,
				phone: '5551234567',
				email,
			} ) ) {
				await page.locator( `#billing_${ key }` ).fill( value );
			}
			await page.locator( '#place_order' ).click();
		} else {
			if ( role === 'guest' ) {
				await page.locator( '#email' ).fill( email );
			}
			await fillBillingCheckoutBlocks( page, billing );
			await page
				.getByRole( 'button', { name: 'Place order', exact: false } )
				.click();
		}

		await expect(
			page.getByRole( 'heading', { name: 'Order received', exact: true } )
		).toBeVisible();
		// The tracking scripts are emitted in wp_footer, after the order heading.
		await page.waitForLoadState( 'load' );
	}
}

module.exports = CheckoutPage;
