// Load the default @wordpress/scripts config object
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

// Use the defaultConfig but replace the entry and output properties
const SetupGuide = {    
    ...defaultConfig,
    entry: {
        index: './assets/source/setup-guide/index.js',
    },
    output: {
        filename: '[name].js',
        path: __dirname + '/assets/setup-guide',
    },
};

const SetupTask = {
    ...defaultConfig,
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
