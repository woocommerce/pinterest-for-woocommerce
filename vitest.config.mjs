import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react-swc';
import { defineConfig } from 'vitest/config';

const source = ( path ) =>
	fileURLToPath( new URL( `./assets/source/${ path }`, import.meta.url ) );

export default defineConfig( {
	plugins: [
		react( {
			// Components and tests keep JSX in `.js` files; the plugin only parses `.jsx` by default.
			parserConfig: ( id ) =>
				/\.jsx?$/.test( id ) && ! id.includes( '/node_modules/' )
					? { syntax: 'ecmascript', jsx: true }
					: undefined,
			disableOxcRecommendation: true,
		} ),
	],
	resolve: {
		alias: [
			// Transform our `.~/` alias.
			{ find: /^\.~\//, replacement: source( '' ) },
			// Insert here a package you want to mock. Then, add it inside tests/dependencies.
			{
				find: /^(@woocommerce\/(?:settings|tracks))$/,
				replacement: source( 'tests/dependencies/$1.js' ),
			},
		],
	},
	test: {
		environment: 'jsdom',
		globals: false,
		restoreMocks: true,
		include: [ 'assets/source/**/*.test.js' ],
		// The console check comes first: Vitest runs `afterEach` hooks in reverse and stops
		// at the first failure, and a failed check must not skip the unmount in the globals.
		setupFiles: [
			'./assets/source/tests/setup-console.js',
			'./assets/source/tests/setup-globals.js',
		],
		coverage: {
			provider: 'v8',
			include: [ 'assets/source/**/*.js' ],
			exclude: [ 'assets/source/**/*.test.js', 'assets/source/tests/**' ],
			reporter: [ 'text', 'html', 'json-summary' ],
		},
	},
} );
