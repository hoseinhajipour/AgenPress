import { render } from '@wordpress/element';
import Panel from './Panel';
import './styles.css';

const mount = document.getElementById( 'agenpress-elementor-root' );

if ( mount ) {
	render( <Panel />, mount );
}
