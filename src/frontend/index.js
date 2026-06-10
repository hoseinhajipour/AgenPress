import apiFetch from '@wordpress/api-fetch';
import { render } from '@wordpress/element';
import Widget from './Widget';
import './styles.css';

apiFetch.use( apiFetch.createNonceMiddleware( window.agenpressChatData?.nonce || '' ) );

const mount = document.getElementById( 'agenpress-chat-widget' );

if ( mount ) {
	const inline = mount.classList.contains( 'agenpress-chat-inline' );
	const position = window.agenpressChatData?.config?.position || 'bottom-right';

	if ( ! inline && position === 'bottom-left' ) {
		mount.classList.add( 'ap-pos-left' );
	}

	render( <Widget inline={ inline } />, mount );
}
