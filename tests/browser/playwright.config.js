const { defineConfig, devices } = require( '@playwright/test' );
const { baseURL } = require( './helpers' );
module.exports = defineConfig( {
	testDir: __dirname,
	testMatch: '*.spec.js',
	workers: 1,
	timeout: 90000,
	expect: { timeout: 15000 },
	use: {
		...devices[ 'Desktop Chrome' ],
		baseURL,
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
	},
	outputDir: '../../test-results/browser',
} );
