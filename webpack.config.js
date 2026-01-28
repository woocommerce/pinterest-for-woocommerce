const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );
const webpack = require( 'webpack' );

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
	new webpack.ProvidePlugin( {
		process: 'process/browser',
	} ),
];

const webpackConfig = {
	...defaultConfig,
	target: 'web',
	plugins: ourPlugins,
	entry: {
		'setup-guide': __dirname + '/assets/source/setup-guide/index.js',
		'product-attributes':
			__dirname + '/assets/source/product-attributes/index.js',
	},
	output: {
		filename: '[name].js',
		path: __dirname + '/assets/build',
		chunkFormat: 'array-push',
	},
	resolve: {
		...defaultConfig.resolve,
		fallback: {
			process: require.resolve( 'process/browser' ),
		},
	},
	optimization: {
		...defaultConfig.optimization,
		// Disable optimizations that cause TDZ errors in complex module graphs.
		concatenateModules: false,
		innerGraph: false,
		usedExports: false,
		// Disable minification to prevent Terser from causing TDZ issues.
		minimize: false,
	},
};

module.exports = webpackConfig;
