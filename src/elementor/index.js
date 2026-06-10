import { createRoot, render } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import Panel from './Panel';
import './styles.css';

const data = window.agenpressElementorData || {};

apiFetch.use( apiFetch.createNonceMiddleware( data.nonce || '' ) );

const mount = document.getElementById( 'agenpress-elementor-root' );

if ( mount ) {
	const app = <Panel />;

	if ( createRoot ) {
		createRoot( mount ).render( app );
	} else {
		render( app, mount );
	}
}
