const { execFileSync } = require( 'node:child_process' );
const path = require( 'node:path' );
const { expect } = require( '@playwright/test' );
const baseURL = process.env.PINTEREST_E2E_URL || 'http://localhost:9011';
function wp( ...args ) {
	const native = process.env.PINTEREST_E2E_WP_PATH;
	if ( native && args[ 0 ] === 'eval-file' )
		args[ 1 ] = path.join( native, args[ 1 ] );
	return execFileSync(
		native ? 'wp' : 'npx',
		native
			? [ `--path=${ native }`, ...args ]
			: [ 'wp-env', 'run', 'tests-cli', 'wp', ...args ],
		{
			cwd: path.resolve( __dirname, '../..' ),
			encoding: 'utf8',
			stdio: [ 'ignore', 'pipe', 'pipe' ],
		}
	).trim();
}
function read( expression ) {
	const output = wp(
		'eval',
		`echo "PINTEREST_RESULT:" . wp_json_encode(${ expression });`
	);
	return JSON.parse( output.split( 'PINTEREST_RESULT:' ).pop() );
}
function seed() {
	wp(
		'eval-file',
		'wp-content/plugins/pinterest-for-woocommerce/tests/browser/seed.php'
	);
	return read( 'get_option("pinterest_e2e_fixture")' );
}
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
async function localNetwork( context ) {
	await context.route( '**/*', async ( route ) => {
		const url = new URL( route.request().url() );
		if ( url.origin === new URL( baseURL ).origin ) return route.continue();
		// Leave the plugin's queued pintrk calls intact; never load Pinterest code.
		if ( url.hostname === 's.pinimg.com' )
			return route.fulfill( {
				contentType: 'application/javascript',
				body: '',
			} );
		return route.abort();
	} );
}
module.exports = { wp, read, seed, login, localNetwork, baseURL };
