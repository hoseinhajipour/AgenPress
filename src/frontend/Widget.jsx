import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { escalateConversation, getSalesSession, sendSalesMessage } from './api';
import MessageContent from './MessageContent';

const data = window.agenpressChatData || {};
const config = data.config || {};
const STORAGE_KEY = 'agenpress_sales_conversation_id';

export default function Widget( { inline = false } ) {
	const [ open, setOpen ] = useState( inline );
	const [ messages, setMessages ] = useState( [] );
	const [ input, setInput ] = useState( '' );
	const [ loading, setLoading ] = useState( false );
	const [ sessionLoading, setSessionLoading ] = useState( !! data.isLoggedIn );
	const [ error, setError ] = useState( null );
	const [ conversationId, setConversationId ] = useState( 0 );
	const [ escalated, setEscalated ] = useState( false );
	const [ hasHistory, setHasHistory ] = useState( false );
	const endRef = useRef( null );

	const color = config.color || '#2271b1';
	const title = config.title || __( 'Chat with us', 'agenpress' );
	const suggestions = data.suggestions || [];

	useEffect( () => {
		endRef.current?.scrollIntoView( { behavior: 'smooth' } );
	}, [ messages, open ] );

	useEffect( () => {
		let cancelled = false;

		const loadSession = async () => {
			if ( data.isLoggedIn ) {
				try {
					const session = await getSalesSession();
					if ( cancelled ) {
						return;
					}

					if ( session.conversation_id ) {
						setConversationId( session.conversation_id );
						localStorage.setItem( STORAGE_KEY, String( session.conversation_id ) );
					}

					if ( Array.isArray( session.messages ) && session.messages.length > 0 ) {
						setMessages( session.messages );
					}

					setEscalated( !! session.escalated );
					setHasHistory( !! session.has_history );
				} catch {
					// Session restore is best-effort.
				} finally {
					if ( ! cancelled ) {
						setSessionLoading( false );
					}
				}
				return;
			}

			const savedId = parseInt( localStorage.getItem( STORAGE_KEY ) || '0', 10 );
			if ( savedId > 0 ) {
				setConversationId( savedId );
			}
			setSessionLoading( false );
		};

		loadSession();

		return () => {
			cancelled = true;
		};
	}, [] );

	useEffect( () => {
		if ( conversationId > 0 ) {
			localStorage.setItem( STORAGE_KEY, String( conversationId ) );
		}
	}, [ conversationId ] );

	const handleSend = async ( text = input ) => {
		if ( ! text.trim() || loading || escalated ) return;

		setMessages( ( prev ) => [ ...prev, { role: 'user', content: text.trim() } ] );
		setInput( '' );
		setLoading( true );
		setError( null );

		try {
			const response = await sendSalesMessage( text.trim(), conversationId );
			if ( response.conversation_id ) {
				setConversationId( response.conversation_id );
			}
			if ( response.message ) {
				setMessages( ( prev ) => [
					...prev,
					{ role: 'assistant', content: response.message.content },
				] );
			}
			if ( response.escalated ) {
				setEscalated( true );
			}
		} catch ( err ) {
			setError( err.message || __( 'Failed to send message.', 'agenpress' ) );
		} finally {
			setLoading( false );
		}
	};

	const handleEscalate = async () => {
		if ( ! conversationId || escalated ) return;

		setLoading( true );
		try {
			await escalateConversation( conversationId );
			setEscalated( true );
			setMessages( ( prev ) => [
				...prev,
				{
					role: 'assistant',
					content: __( 'Your request has been sent to our team. We will get back to you soon.', 'agenpress' ),
				},
			] );
		} catch ( err ) {
			setError( err.message );
		} finally {
			setLoading( false );
		}
	};

	if ( ! inline && ! open ) {
		return (
			<button
				className="ap-chat-toggle"
				style={ { background: color } }
				onClick={ () => setOpen( true ) }
				aria-label={ title }
			>
				💬
			</button>
		);
	}

	return (
		<div className={ `ap-chat-widget ${ inline ? 'inline' : '' }` }>
			<div className="ap-chat-header" style={ { background: color } }>
				<h4>{ title }</h4>
				{ ! inline && (
					<button className="ap-chat-close" onClick={ () => setOpen( false ) }>×</button>
				) }
			</div>

			{ error && <div className="ap-chat-error">{ error }</div> }
			{ escalated && (
				<div className="ap-chat-escalated">
					{ __( 'A team member will follow up with you.', 'agenpress' ) }
				</div>
			) }

			<div className="ap-chat-messages">
				{ sessionLoading && (
					<div className="ap-chat-msg assistant ap-chat-loading">
						<span className="ap-chat-typing">
							<span /><span /><span />
						</span>
					</div>
				) }
				{ ! sessionLoading && messages.length === 0 && (
					<div className="ap-chat-msg assistant">
						<MessageContent
							role="assistant"
							content={
								hasHistory
									? __( 'Welcome back! I remember our previous chats. How can I help you today?', 'agenpress' )
									: __( 'Hi! How can I help you today?', 'agenpress' )
							}
						/>
					</div>
				) }
				{ messages.map( ( msg, i ) => (
					<div key={ i } className={ `ap-chat-msg ${ msg.role }` }>
						<MessageContent role={ msg.role } content={ msg.content } />
					</div>
				) ) }
				{ loading && (
					<div className="ap-chat-msg assistant ap-chat-loading">
						<span className="ap-chat-typing">
							<span /><span /><span />
						</span>
					</div>
				) }
				<div ref={ endRef } />
			</div>

			{ messages.length === 0 && suggestions.length > 0 && (
				<div className="ap-chat-suggestions">
					{ suggestions.slice( 0, 3 ).map( ( s, i ) => (
						<button key={ i } className="ap-chat-suggestion" onClick={ () => handleSend( s ) }>
							{ s }
						</button>
					) ) }
				</div>
			) }

			<div className="ap-chat-input-row">
				<textarea
					className="ap-chat-input"
					value={ input }
					onChange={ ( e ) => setInput( e.target.value ) }
					onKeyDown={ ( e ) => {
						if ( e.key === 'Enter' && ! e.shiftKey ) {
							e.preventDefault();
							handleSend();
						}
					} }
					placeholder={ __( 'Type a message...', 'agenpress' ) }
					disabled={ loading || escalated || sessionLoading }
					rows={ 1 }
				/>
				<button
					className="ap-chat-send"
					style={ { background: color } }
					onClick={ () => handleSend() }
					disabled={ loading || escalated || sessionLoading || ! input.trim() }
				>
					{ __( 'Send', 'agenpress' ) }
				</button>
			</div>

			<div className="ap-chat-footer">
				<span>{ __( 'Powered by AgenPress', 'agenpress' ) }</span>
				{ conversationId > 0 && ! escalated && (
					<button className="ap-chat-escalate" onClick={ handleEscalate }>
						{ __( 'Talk to a human', 'agenpress' ) }
					</button>
				) }
			</div>
		</div>
	);
}
