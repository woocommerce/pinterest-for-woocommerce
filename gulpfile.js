let nodegit, FOLDERS, DOMAIN, PATHS, MATCH, SRC;

const gulp = require( 'gulp' );
const merge = require( 'merge-stream' );
const fs = require( 'fs' );
const path = require( 'path' );
// eslint-disable-next-line no-unused-vars -- Retain the existing build dependency during lint adoption.
const semver = require( 'semver' );
const _ = require( 'underscore' );
const $ = require( 'gulp-load-plugins' )( {
	pattern: [ '*', '!sass', '!gulp-sass' ],
} );
$.sass = require( 'gulp-sass' )( require( 'sass' ) );

const argv = require( 'minimist' )( process.argv.slice( 2 ) );
const through = require( 'through2' );

if ( path.sep !== '/' ) {
	const oldJoin = path.join;
	path.join = function () {
		const ret = oldJoin.apply( this, arguments );
		return ret.replace( new RegExp( '\\' + path.sep, 'g' ), '/' );
	};
}

const CONFIG = {
	production: !! argv.production,
	watch: !! argv.watch,
	bs: !! argv.bs,
	noprefix: !! argv.noprefix,
};

// Here are defined relative paths for source files, dest paths and maps
// Change them if you know what you are doing or just stick to folder structure convention
PATHS = {
	assets: '/assets',
	source: '{self.assets}/source',
	sass: '{self.assets}/source/sass',
	jsSource: '{self.assets}/source/js',
	css: '{self.assets}/css',
	jsDest: '{self.assets}/js',
	maps: '{self.assets}/source/_maps',
};

MATCH = {
	php: '**/*.php',
	sass: '**/*.scss',
	css: '**/*.css',
	js: '**/*.js',
};

SRC = {
	sass: [],
	js: [],
};

/* Confing: Edit project-folders.json to set your folders and domain
========================================================= */
try {
	FOLDERS = JSON.parse( fs.readFileSync( './project-folders.json' ) );
} catch ( e ) {
	FOLDERS = [ '.' ];
}

Array.prototype.getFiltered = function ( type ) {
	if ( type ) {
		return this.filter( function ( item ) {
			return item.name.substr( 0, type.length ) === type;
		} );
	}
	return this;
};

Array.prototype.getFlattened = function ( prefix ) {
	if ( typeof prefix === 'undefined' ) {
		prefix = '';
	}

	const paths = [];
	this.forEach( function ( item ) {
		item.files.forEach( function ( file ) {
			paths.push( prefix + file.f );
		} );
	} );
	return paths;
};

const newFolders = [];
_.each( FOLDERS, function ( item, key ) {
	if ( typeof item === 'string' ) {
		item = { folder: item };
	} else if ( typeof item === 'object' ) {
		if ( typeof item.folder === 'string' ) {
			// do nothing, we have a folder defined
		} else if ( typeof key === 'string' ) {
			// we're on an object based structure
			item.folder = key;
		} else {
			// We don't have a folder defined, we are editing globals here
			if ( ! _.isUndefined( item.PATHS ) ) {
				PATHS = _.extend( {}, PATHS, item.PATHS );
			}
			if ( ! _.isUndefined( item.MATCH ) ) {
				MATCH = _.extend( {}, MATCH, item.MATCH );
			}
			if ( ! _.isUndefined( item.SRC ) ) {
				SRC = _.extend( {}, SRC, item.SRC );
			}
			return; // ignore because we have no folder defined
		}
	}
	if ( _.isUndefined( item.PATHS ) ) {
		item.PATHS = {};
	}
	item.PATHS = _.extend( {}, PATHS, item.PATHS );
	_.each( item.PATHS, function ( val, pathKey, self ) {
		const newPath = val.replace(
			/\{self\.(.*?)\}/g,
			function ( match, g1 ) {
				return self[ g1 ];
			}
		);
		self[ pathKey ] = newPath;
	} );

	if ( _.isUndefined( item.MATCH ) ) {
		item.MATCH = {};
	}
	item.MATCH = _.extend( {}, MATCH, item.MATCH );

	if ( _.isUndefined( item.SRC ) ) {
		item.SRC = {};
	}
	item.SRC = _.extend( {}, SRC, item.SRC );

	if ( _.isUndefined( item.concat ) ) {
		try {
			item.concat = JSON.parse(
				fs.readFileSync(
					path.join( item.folder, item.PATHS.source, 'concat.json' )
				)
			);
		} catch ( e ) {
			item.concat = [];
		}
	}

	item.concat.forEach( function ( concatDest ) {
		concatDest.files = concatDest.files.map( function ( source ) {
			if ( typeof source === 'string' ) {
				source = { f: source };
			}
			let prefix = '';
			if ( source.f.substr( 0, 1 ) === '!' ) {
				prefix = '!';
				source.f = source.f.substr( 1 );
			}
			source.f =
				prefix + path.join( item.folder, item.PATHS.source, source.f );

			return source;
		} );
	} );

	newFolders.push( item );
} );
FOLDERS = newFolders;

gulp.task( 'zip', function () {
	const tasks = FOLDERS.map( function ( folderConfig ) {
		const folder = folderConfig.folder;
		const basename = path.basename( path.resolve( folder ) );
		const filename = path.join( folder, basename + '.zip' );
		try {
			fs.unlinkSync( filename );
		} catch ( e ) {
			// do nothing
		}
		return gulp
			.src(
				[
					`${ folder }/{assets,i18n,includes,src,vendor,views}/**/*`,
					`${ folder }/*.{php,txt,md}`,
					'LICENSE',
					`!${ folder }/README.md`,
					`!${ folder }/i18n/languages/README.md`,
				],
				{
					base: path.join( folder, '..' ),
					encoding: false,
				}
			)
			.pipe( $.vinylZip.dest( filename ) )
			.pipe( $.size( { title: folder + ' zip' } ) );
	} );
	return merge( tasks );
} );

function map_destination( folderConfig, dest ) {
	const assetsPath = path.join(
		folderConfig.folder,
		folderConfig.PATHS.assets
	);
	const destPath = path.join( folderConfig.folder, dest );
	const mapsPath = path.join( folderConfig.folder, folderConfig.PATHS.maps );
	const destRelative = path.relative( assetsPath, destPath );

	let suffix = '';
	if (
		destRelative.substr( 0, 1 ) !== '.' &&
		destRelative.substr( 0, 1 ) !== path.sep
	) {
		suffix = path.sep + destRelative;
	}

	return {
		dest: destPath,
		maps: path.relative( destPath, mapsPath ) + suffix,
	};
}

gulp.task( 'sass', function () {
	const tasks = FOLDERS.map( function ( folderConfig ) {
		const folder = folderConfig.folder;
		const folderPaths = folderConfig.PATHS;
		const folderMatch = folderConfig.MATCH;
		const folderSources = JSON.parse(
			JSON.stringify( folderConfig.SRC.sass )
		);
		const baseSRC = path.join( folder, folderPaths.sass );
		folderSources.unshift( path.join( baseSRC, folderMatch.sass ) );

		const destination = map_destination( folderConfig, folderPaths.css );

		return gulp
			.src( folderSources, { base: baseSRC } )
			.pipe( $.plumber() )
			.pipe( $.sourcemaps.init() )
			.pipe( $.sass( { precision: 10 } ).on( 'error', $.sass.logError ) )
			.pipe( ! CONFIG.noprefix ? $.autoprefixer() : through.obj() )
			.pipe(
				! CONFIG.production
					? $.sourcemaps.write( destination.maps )
					: through.obj()
			)
			.pipe( $.cached( 'sass' ) )
			.pipe( gulp.dest( destination.dest ) )
			.pipe( $.filter( '**/*.css' ) )
			.pipe( $.cleanCss() )
			.pipe( $.rename( { suffix: '.min' } ) )
			.pipe(
				! CONFIG.production
					? $.sourcemaps.write( destination.maps )
					: through.obj()
			)
			.pipe( $.cached( 'sass' ) )
			.pipe( gulp.dest( destination.dest ) )
			.pipe( $.size( { title: folder + ' css' } ) );
	} );
	return merge( tasks );
} );

gulp.task( 'js', function () {
	const tasks = FOLDERS.map( function ( folderConfig ) {
		const folder = folderConfig.folder;
		const folderPaths = folderConfig.PATHS;
		const folderMatch = folderConfig.MATCH;
		let folderSources = JSON.parse( JSON.stringify( folderConfig.SRC.js ) );
		const baseSRC = path.join( folder, folderPaths.jsSource );
		folderSources.unshift( path.join( baseSRC, folderMatch.js ) );

		const destination = map_destination( folderConfig, folderPaths.jsDest );

		// get concat sources for this particular folder (from the source folder)
		const concatFiles = folderConfig.concat.getFiltered( 'js' );
		// use the concat sources as ignored paths in the "base" process
		const includePaths = concatFiles.getFlattened( '!' );
		folderSources = _.union( folderSources, includePaths );

		const folderTasks = [];

		// Do everything not included in the concat
		const base = gulp
			.src( folderSources, { base: baseSRC } )
			.pipe( $.plumber() )
			.pipe( $.sourcemaps.init() )
			.pipe(
				! CONFIG.production
					? $.sourcemaps.write( destination.maps )
					: through.obj()
			)
			.pipe( $.cached( 'js' ) )
			.pipe( gulp.dest( destination.dest ) )
			.pipe( $.filter( [ '**/*.js', '!**/*.min.js' ] ) )
			.pipe( $.uglify() )
			.pipe( $.rename( { suffix: '.min' } ) )
			.pipe(
				! CONFIG.production
					? $.sourcemaps.write( destination.maps )
					: through.obj()
			)
			.pipe( $.cached( 'js' ) )
			.pipe( gulp.dest( destination.dest ) )
			.pipe( $.size( { title: folder + ' js' } ) );

		folderTasks.push( base );

		// Process each concat dest as it's individual file
		concatFiles.forEach( function ( dest ) {
			let thisDest;

			if ( dest.passthrough ) {
				let passThroughDest = destination;
				if (
					dest.files.length > 1 ||
					dest.files[ 0 ].f.indexOf( '*' ) > -1
				) {
					passThroughDest = map_destination(
						folderConfig,
						path.join(
							folderPaths.jsDest,
							path.basename( dest.name )
						)
					);
				}
				thisDest = gulp
					.src( [ dest ].getFlattened() )
					.pipe( $.plumber() )
					.pipe( $.cached( 'js' ) )
					.pipe( gulp.dest( passThroughDest.dest ) )
					.pipe(
						$.size( {
							title: folder + ' passthrough ' + dest.name,
						} )
					);
			} else {
				const thisDestPath = path.join(
					folderConfig.PATHS.assets,
					path.dirname( dest.name )
				);
				const thisDestination = map_destination(
					folderConfig,
					thisDestPath
				);

				let keepUnminified = _.pluck(
					_.filter( dest.files, 'keepUnminified' ),
					'f'
				);
				keepUnminified = keepUnminified.length
					? $.filter( keepUnminified, { restore: true } )
					: null;
				thisDest = gulp
					.src( [ dest ].getFlattened() )
					.pipe( $.plumber() )
					.pipe( $.sourcemaps.init() )
					.pipe( keepUnminified ? keepUnminified : through.obj() )
					.pipe(
						keepUnminified && ! CONFIG.production
							? $.sourcemaps.write( thisDestination.maps )
							: through.obj()
					)
					.pipe( keepUnminified ? $.cached( 'js' ) : through.obj() )
					.pipe(
						keepUnminified
							? gulp.dest( thisDestination.dest )
							: through.obj()
					)
					.pipe(
						keepUnminified ? keepUnminified.restore : through.obj()
					)
					.pipe( $.filter( [ '**/*.js' ] ) )
					.pipe( $.concat( path.basename( dest.name ) ) )
					.pipe(
						! dest.minifiedOnly && ! CONFIG.production
							? $.sourcemaps.write( thisDestination.maps )
							: through.obj()
					)
					.pipe(
						! dest.minifiedOnly ? $.cached( 'js' ) : through.obj()
					)
					.pipe(
						! dest.minifiedOnly
							? gulp.dest( thisDestination.dest )
							: through.obj()
					)
					.pipe( $.filter( [ '**/*.js' ] ) )
					.pipe( $.uglify() )
					.pipe( $.rename( { suffix: '.min' } ) )
					.pipe(
						! CONFIG.production
							? $.sourcemaps.write( thisDestination.maps )
							: through.obj()
					)
					.pipe( $.cached( 'js' ) )
					.pipe( gulp.dest( thisDestination.dest ) )
					.pipe(
						$.size( { title: folder + ' concat ' + dest.name } )
					);
			}

			folderTasks.push( thisDest );
		} );

		return merge( folderTasks );
	} );
	return merge( tasks );
} );

gulp.task( 'browser-sync', function ( done ) {
	const files = [];
	FOLDERS.forEach( function ( folderConfig ) {
		files.push(
			path.join(
				folderConfig.folder,
				folderConfig.PATHS.css,
				folderConfig.MATCH.css
			)
		);
		files.push(
			path.join(
				folderConfig.folder,
				folderConfig.PATHS.jsDest,
				folderConfig.MATCH.js
			)
		);
		files.push( path.join( folderConfig.folder, folderConfig.MATCH.php ) );
	} );

	try {
		DOMAIN = JSON.parse( fs.readFileSync( './dev-domain.json' ) );
	} catch ( e ) {
		return done( e );
	}
	$.browserSync.init( files, { proxy: DOMAIN } );
	return done();
} );

// eslint-disable-next-line no-unused-vars -- Gulp uses callback arity to recognize this long-running watch task.
const doWatch = function ( done ) {
	FOLDERS.forEach( function ( folderConfig ) {
		gulp.watch(
			path.join(
				folderConfig.folder,
				folderConfig.PATHS.sass,
				folderConfig.MATCH.sass
			),
			gulp.parallel( 'sass' )
		);
		gulp.watch(
			path.join(
				folderConfig.folder,
				folderConfig.PATHS.jsSource,
				folderConfig.MATCH.js
			),
			gulp.parallel( 'js' )
		);
	} );
};
let watch_task;
if ( CONFIG.bs ) {
	watch_task = gulp.series( 'browser-sync', doWatch );
} else {
	watch_task = doWatch;
}

gulp.task( 'watch', watch_task );

gulp.task( 'build', gulp.parallel( 'sass', 'js' ) );

let default_task;
if ( CONFIG.watch ) {
	default_task = gulp.series( 'build', 'watch' );
} else {
	default_task = gulp.series( 'build' );
}

// eslint-disable-next-line no-unused-vars -- Preserve the existing optional helper during lint adoption.
async function getDiffFiles() {
	nodegit = require( 'nodegit' );
	const repository = await nodegit.Repository.open( '.' );
	const currCommit = await repository.getHeadCommit();
	const mastCommit = await repository.getMasterCommit();

	const currTree = await currCommit.getTree();
	const mastTree = await mastCommit.getTree();

	let diff = await currTree.diff( mastTree );
	diff = await diff.patches();

	diff = diff
		.map( function ( diffFile ) {
			if ( diffFile.isDeleted() ) {
				return false;
			}
			return '.' + path.sep + diffFile.newFile().path();
		} )
		.filter( function ( cont ) {
			return cont ? true : false;
		} );
	return diff;
}

gulp.task( 'default', default_task );
