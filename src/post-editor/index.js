import { render } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import apiFetch from '@wordpress/api-fetch';
import FeaturedImageButton from './FeaturedImageButton';
import ClassicEditorBridge from './ClassicEditorBridge';
import './styles.css';

const data = window.agenpressPostEditorData || {};

apiFetch.use( apiFetch.createNonceMiddleware( data.nonce ) );
apiFetch.use( apiFetch.createRootURLMiddleware( data.apiUrl ) );

const classicMount = document.getElementById( 'agenpress-featured-image-root' );

if ( classicMount ) {
	const postId = parseInt( classicMount.dataset.postId || data.postId || '0', 10 );

	render(
		<FeaturedImageButton editorType="classic" postId={ postId } />,
		classicMount
	);
}

const classicEditorRoot = document.getElementById( 'agenpress-classic-editor-root' );

if ( classicEditorRoot ) {
	render( <ClassicEditorBridge />, classicEditorRoot );
}

addFilter(
	'editor.PostFeaturedImage',
	'agenpress/featured-image-ai',
	( OriginalComponent ) => {
		return ( props ) => (
			<>
				<OriginalComponent { ...props } />
				<FeaturedImageButton editorType="block" />
			</>
		);
	}
);
