// Load the default @wordpress/scripts config object
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );
const path = require( 'path' );

const requestToExternal = ( request ) => {
	// The following default externals are bundled for compatibility with older versions of WP
	// Note CSS for specific components is bundled via admin/assets/src/index.scss
	// WP 5.4 is the min version for <Card* />, <TabPanel />
	const bundled = [
		'@wordpress/compose',
		'@wordpress/components',
		'@wordpress/primitives',
	];
	if ( bundled.includes( request ) ) {
		return false;
	}

	const wcDepMap = {
		'@woocommerce/navigation': [ 'wc', 'navigation' ],
	};

	return wcDepMap[ request ];
};

const requestToHandle = ( request ) => {
	const wcHandleMap = {
		'@woocommerce/components': 'wc-components',
		'@woocommerce/navigation': 'wc-navigation',
	};

	return wcHandleMap[ request ];
};

// Use the defaultConfig but replace the entry and output properties
const SetupGuide = {
    ...defaultConfig,
	resolve: {
		...defaultConfig.resolve,
		alias: {
			'.~': path.resolve( process.cwd(), 'assets/source/' ),
		},
		// Resolve jsx/tsx files for `@woocommerce/data`...`/with-plugins-hydration`
		extensions: [ '.js', '.jsx', '.json' ],
	},
    plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new DependencyExtractionWebpackPlugin( {
			injectPolyfill: true,
			requestToExternal,
			requestToHandle,
		} ),
	],
    entry: {
        index: './assets/source/setup-guide/index.js',
    },
    output: {
		...defaultConfig.output,
        filename: '[name].js',
        path: __dirname + '/assets/setup-guide',
    },
};

const SetupTask = {
    ...defaultConfig,
	resolve: {
		...defaultConfig.resolve,
		alias: {
			'.~': path.resolve( process.cwd(), 'assets/source/' ),
		},
		// Resolve jsx/tsx files for `@woocommerce/data`...`/with-plugins-hydration`
		extensions: [ '.ts', '.tsx', '.js', '.jsx', '.json' ],
	},
    plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new DependencyExtractionWebpackPlugin( {
			injectPolyfill: true,
			requestToExternal,
			requestToHandle,
		} ),
	],
    entry: {
        index: './assets/source/setup-task/index.js',
    },
    output: {
        filename: '[name].js',
        path: __dirname + '/assets/setup-task',
    },
};

module.exports = [
    SetupGuide,
    SetupTask
];
