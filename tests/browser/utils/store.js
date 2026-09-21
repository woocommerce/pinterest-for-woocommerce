const { execFile } = require( 'node:child_process' );
const { promisify } = require( 'node:util' );
const path = require( 'node:path' );

const execute = promisify( execFile );
const commandFile =
	'wp-content/plugins/pinterest-for-woocommerce/tests/browser/php/command.php';

/** WordPress data operations for the dedicated browser test store. */
class Store {
	async seed() {
		this.fixture = await this.#run( 'seed' );
		return this.fixture;
	}

	cleanup() {
		return this.#run( 'cleanup' );
	}

	prepareCheckout( options ) {
		return this.#run( 'prepare-checkout', options );
	}

	allowConnection() {
		return this.#run( 'allow-connection' );
	}

	connectionState() {
		return this.#run( 'connection-state' );
	}

	orders() {
		return this.#run( 'orders' );
	}

	events() {
		return this.#run( 'events' );
	}

	unexpectedHttp() {
		return this.#run( 'unexpected-http' );
	}

	async #run( command, options = {} ) {
		const native = process.env.PINTEREST_E2E_WP_PATH;
		const args = [
			'eval-file',
			native ? path.join( native, commandFile ) : commandFile,
			command,
			JSON.stringify( options ),
		];
		const { stdout } = await execute(
			native ? 'wp' : 'npx',
			native
				? [ `--path=${ native }`, ...args ]
				: [
						'--no-install',
						'wp-env',
						'run',
						'tests-cli',
						'wp',
						...args,
					],
			{ cwd: path.resolve( __dirname, '../../..' ) }
		);
		const prefix = 'PINTEREST_RESULT:';
		const result = stdout
			.split( /\r?\n/ )
			.find( ( line ) => line.startsWith( prefix ) );
		if ( ! result )
			throw new Error(
				`Browser store command "${ command }" returned no result.`
			);
		return JSON.parse( result.slice( prefix.length ) );
	}
}

module.exports = Store;
