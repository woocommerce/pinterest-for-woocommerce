const Store = require( './utils/store' );

new Store().cleanup().catch( ( error ) => {
	process.stderr.write( `${ error.message }\n` );
	process.exitCode = 1;
} );
