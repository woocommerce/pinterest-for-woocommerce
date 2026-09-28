const { defineConfig, devices } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './specs',
	// PHP fixtures share one dedicated store, so run journeys sequentially.
	workers: 1,
	fullyParallel: false,
	forbidOnly: !! process.env.CI,
	timeout: 90000,
	expect: { timeout: 15000 },
	use: {
		...devices[ 'Desktop Chrome' ],
		baseURL: process.env.PINTEREST_E2E_URL || 'http://localhost:9011',
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
	},
	outputDir: '../../test-results/browser',
} );
