const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: path.resolve( process.cwd(), 'src/admin', 'index.js' ),
		'elementor-editor': path.resolve( process.cwd(), 'src/elementor', 'index.js' ),
		'frontend-chat': path.resolve( process.cwd(), 'src/frontend', 'index.js' ),
	},
};
