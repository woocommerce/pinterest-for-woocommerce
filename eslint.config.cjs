const woocommerce = require( '@woocommerce/eslint-plugin' );

const jsdocTypes = woocommerce.configs.recommended.find(
	( config ) => config.rules?.[ 'jsdoc/no-undefined-types' ]?.[ 1 ]?.definedTypes
).rules[ 'jsdoc/no-undefined-types' ][ 1 ].definedTypes;

module.exports = [
	...woocommerce.configs.recommended,
	{
		settings: {
			react: { version: '16.14' },
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
];
