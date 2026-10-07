const vitest = require( '@vitest/eslint-plugin' );
const woocommerce = require( '@woocommerce/eslint-plugin' );

const jsdocTypes = woocommerce.configs.recommended.find(
	( config ) =>
		config.rules?.[ 'jsdoc/no-undefined-types' ]?.[ 1 ]?.definedTypes
).rules[ 'jsdoc/no-undefined-types' ][ 1 ].definedTypes;

// The shared preset lints every test file as Jest. Collect what it turns on, so
// that the unit tests can swap it for the Vitest equivalent below.
const jestConfigs = woocommerce.configs.recommended.filter( ( config ) =>
	Object.keys( config.rules ?? {} ).some( ( rule ) =>
		rule.startsWith( 'jest/' )
	)
);
const off = ( names ) =>
	Object.fromEntries( names.map( ( n ) => [ n, 'off' ] ) );

module.exports = [
	...woocommerce.configs.recommended,
	{
		settings: {
			react: { version: '16.14' },
			// The shared preset still applies Jest rules to the Playwright tests,
			// and they cannot detect a version now that Jest is not installed.
			jest: { version: 30 },
		},
		rules: {
			// Keep the previous lint policy during the dependency migration.
			'@typescript-eslint/no-use-before-define': 'off',
			'@typescript-eslint/no-unused-vars': 'off',
			'no-unused-vars': [
				'error',
				{ caughtErrors: 'none', ignoreRestSiblings: false },
			],
			'@typescript-eslint/no-require-imports': 'off',
			'@wordpress/i18n-no-flanking-whitespace': 'off',
			'@wordpress/i18n-text-domain': [ 'error', {} ],
			'testing-library/await-async-events': 'off',
			curly: 'off',
			// React 16 JSX and tracking events are documented across source files.
			'jsdoc/no-undefined-types': [
				'error',
				{
					definedTypes: [
						...jsdocTypes,
						'JSX',
						'wcadmin_pfw_modal_close',
						'wcadmin_pfw_modal_closed',
						'wcadmin_pfw_modal_open',
						'wcadmin_pfw_get_started_notice_link_click',
						'wcadmin_pfw_documentation_link_click',
						'wcadmin_pfw_setup',
					],
				},
			],
		},
	},
	{
		files: [ 'assets/source/**/*.test.js', 'assets/source/tests/**/*.js' ],
		plugins: { vitest },
		languageOptions: {
			// Vitest runs without globals: its API has to be imported.
			globals: off(
				jestConfigs.flatMap( ( config ) =>
					Object.keys( config.languageOptions?.globals ?? {} )
				)
			),
		},
		rules: {
			...off(
				jestConfigs
					.flatMap( ( config ) => Object.keys( config.rules ) )
					.filter( ( rule ) => rule.startsWith( 'jest/' ) )
			),
			...vitest.configs.recommended.rules,
		},
	},
	{
		files: [ 'gulpfile.js', 'webpack.config.js', 'eslint.config.cjs' ],
		languageOptions: {
			sourceType: 'commonjs',
		},
	},
];
