import { render } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import Panel from './Panel';
import './styles.css';

const data = window.agenpressElementorData || {};

apiFetch.use( apiFetch.createNonceMiddleware( data.nonce || '' ) );

const mount = document.getElementById( 'agenpress-elementor-root' );

if ( mount ) {
	render( <Panel />, mount );
}
