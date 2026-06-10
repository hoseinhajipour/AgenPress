import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { getElementorSelection, subscribeToSelection } from './selection';

const data = window.agenpressElementorData || {};

async function sendElementorMessage( message, conversationId, context ) {
	const res = await apiFetch( {
		path: '/chat/elementor',
		method: 'POST',
		data: {
			message,
			conversation_id: conversationId,
			context,
		},
	} );
	return res.data;
}

async function confirmElementorAction( pendingId, conversationId ) {
	const res = await apiFetch( {
		path: '/chat/elementor/confirm',
		method: 'POST',
		data: {
			pending_id: pendingId,
			conversation_id: conversationId,
		},
	} );
	return res.data;
}

function formatSelection( selection ) {
	if ( ! selection.element_id ) {
		return __( 'No element selected', 'agenpress' );
	}
	const parts = [ selection.element_id ];
	if ( selection.el_type ) {
		parts.push( selection.el_type );
	}
	if ( selection.widget_type ) {
		parts.push( selection.widget_type );
	}
	return parts.join( ' · ' );
}

export default function Panel() {
	const [ collapsed, setCollapsed ] = useState( false );
	const [ messages, setMessages ] = useState( [] );
	const [ input, setInput ] = useState( '' );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ conversationId, setConversationId ] = useState( 0 );
	const [ selection, setSelection ] = useState( getElementorSelection );
	const [ pendingAction, setPendingAction ] = useState( null );
	const messagesEndRef = useRef( null );

	const suggestions = data.suggestions || [];

	useEffect( () => {
		apiFetch.use( apiFetch.createNonceMiddleware( data.nonce ) );
		apiFetch.use( apiFetch.createRootURLMiddleware( data.apiUrl ) );
	}, [] );

	useEffect( () => subscribeToSelection( setSelection ), [] );

	useEffect( () => {
		messagesEndRef.current?.scrollIntoView( { behavior: 'smooth' } );
	}, [ messages, pendingAction ] );

	const handleResponse = ( response ) => {
		if ( response.conversation_id ) {
			setConversationId( response.conversation_id );
		}
		if ( response.message ) {
			setMessages( ( prev ) => [
				...prev,
				{ role: 'assistant', content: response.message.content },
			] );
		}
		if ( response.pending_actions?.length > 0 ) {
			setPendingAction( response.pending_actions[ 0 ] );
		}
	};

	const handleSend = async ( text = input ) => {
		if ( ! text.trim() || loading ) {
			return;
		}

		const context = getElementorSelection();
		setMessages( ( prev ) => [ ...prev, { role: 'user', content: text.trim() } ] );
		setInput( '' );
		setLoading( true );
		setError( null );
		setPendingAction( null );

		try {
			const response = await sendElementorMessage( text.trim(), conversationId, context );
			handleResponse( response );

			if ( response.pending_actions?.length ) {
				return;
			}

			// Reload preview after structural changes.
			window.elementor?.reloadPreview?.();
		} catch ( err ) {
			setError( err.message || __( 'Failed to send message.', 'agenpress' ) );
		} finally {
			setLoading( false );
		}
	};

	const handleConfirm = async () => {
		if ( ! pendingAction || ! conversationId ) {
			return;
		}

		setLoading( true );
		setError( null );

		try {
			const response = await confirmElementorAction( pendingAction.id, conversationId );
			setPendingAction( null );
			handleResponse( response );
			window.elementor?.reloadPreview?.();
		} catch ( err ) {
			setError( err.message );
		} finally {
			setLoading( false );
		}
	};

	return (
		<div className={ `ap-el-panel ${ collapsed ? 'collapsed' : '' }` }>
			<div className="ap-el-header" onClick={ () => setCollapsed( ! collapsed ) }>
				<h4>{ __( 'AgenPress AI', 'agenpress' ) }</h4>
				<span>{ collapsed ? '▲' : '▼' }</span>
			</div>

			{ ! collapsed && (
				<div className="ap-el-body">
					<div className="ap-el-selection">
						<strong>{ __( 'Selection:', 'agenpress' ) }</strong> { formatSelection( selection ) }
					</div>

					{ error && <div className="ap-el-error">{ error }</div> }

					<div className="ap-el-messages">
						{ messages.length === 0 && (
							<div className="ap-el-msg assistant">
								{ __( 'Ask me to design sections, update widgets, or generate images for your page.', 'agenpress' ) }
							</div>
						) }
						{ messages.map( ( msg, i ) => (
							<div key={ i } className={ `ap-el-msg ${ msg.role }` }>
								{ msg.content }
							</div>
						) ) }
						{ pendingAction && (
							<div className="ap-el-msg assistant">
								<p>{ pendingAction.message }</p>
								<button className="ap-el-send" onClick={ handleConfirm } disabled={ loading }>
									{ __( 'Confirm', 'agenpress' ) }
								</button>
							</div>
						) }
						<div ref={ messagesEndRef } />
					</div>

					{ messages.length === 0 && suggestions.length > 0 && (
						<div className="ap-el-suggestions">
							{ suggestions.slice( 0, 3 ).map( ( s, i ) => (
								<button
									key={ i }
									className="ap-el-suggestion"
									onClick={ () => handleSend( s ) }
								>
									{ s }
								</button>
							) ) }
						</div>
					) }

					{ loading && <div className="ap-el-loading">{ __( 'Thinking...', 'agenpress' ) }</div> }

					<div className="ap-el-input-row">
						<textarea
							className="ap-el-input"
							value={ input }
							onChange={ ( e ) => setInput( e.target.value ) }
							onKeyDown={ ( e ) => {
								if ( e.key === 'Enter' && ! e.shiftKey ) {
									e.preventDefault();
									handleSend();
								}
							} }
							placeholder={ __( 'Ask about this page...', 'agenpress' ) }
							disabled={ loading }
						/>
						<button
							className="ap-el-send"
							onClick={ () => handleSend() }
							disabled={ loading || ! input.trim() }
						>
							{ __( 'Send', 'agenpress' ) }
						</button>
					</div>
				</div>
			) }
		</div>
	);
}
