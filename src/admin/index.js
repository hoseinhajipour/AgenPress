import apiFetch from '@wordpress/api-fetch';
import './styles.css';
import App from './App';

apiFetch.use( apiFetch.createNonceMiddleware( window.agenpressData?.nonce || '' ) );
apiFetch.use( apiFetch.createRootURLMiddleware( window.agenpressData?.apiUrl || '/wp-json/agenpress/v1/' ) );

const root = document.getElementById( 'agenpress-root' );
if ( root ) {
	const { createRoot, render } = wp.element;
	const app = <App />;

	if ( createRoot ) {
		createRoot( root ).render( app );
	} else {
		render( app, root );
	}
}
