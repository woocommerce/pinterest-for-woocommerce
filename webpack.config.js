const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );

const requestToExternal = ( request ) => {
	// Bundle these packages & components so we can use the latest, independent of WordPress version.
	// Without bundling these specific recent versions, components like LandingPageApp don't render correctly.
	const bundled = [ '@wordpress/components', '@wordpress/compose' ];
	if ( bundled.includes( request ) ) {
		return false;
	}
};

// Replace the default DependencyExtractionWebpackPlugin with the Woo version
// and override to bundle specific newer packages (see requestToExternal above).
const ourPlugins = [
	...defaultConfig.plugins.filter(
		( plugin ) =>
			plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
	),
	new WooCommerceDependencyExtractionWebpackPlugin( {
		injectPolyfill: true, // TBD Confirm this is needed for Pinterest.
		requestToExternal,
	} ),
];

const webpackConfig = {
	...defaultConfig,
	plugins: ourPlugins,
	entry: {
		'setup-guide': __dirname + '/assets/source/setup-guide/index.js',
		'setup-task': __dirname + '/assets/source/setup-task/index.js',
		'product-attributes':
			__dirname + '/assets/source/product-attributes/index.js',
	},
	output: {
		filename: '[name].js',
		path: __dirname + '/assets/build',
	},
};

module.exports = webpackConfig;
