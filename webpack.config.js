const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	output: {
		...defaultConfig.output,
		path: require( 'path' ).resolve( process.cwd(), 'assets/js' ),
	},
	entry: {
		admin: path.resolve( process.cwd(), 'src/admin', 'index.js' ),
		'elementor-editor': path.resolve( process.cwd(), 'src/elementor', 'index.js' ),
		'frontend-chat': path.resolve( process.cwd(), 'src/frontend', 'index.js' ),
		'post-editor': path.resolve( process.cwd(), 'src/post-editor', 'index.js' ),
		'classic-editor-tinymce': path.resolve( process.cwd(), 'src/post-editor', 'classic-editor-tinymce.js' ),
	},
};
