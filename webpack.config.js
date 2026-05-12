/**
 * Webpack config: emit the admin bundle into `plugin/build` so the repository
 * root stays development-only while `adbot/` matches what WordPress loads.
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const outputPath = path.resolve( __dirname, 'adbot/build' );

const withPluginOutput = ( config ) => ( {
	...config,
	output: {
		...config.output,
		path: outputPath,
	},
} );

module.exports = Array.isArray( defaultConfig )
	? defaultConfig.map( withPluginOutput )
	: withPluginOutput( defaultConfig );
