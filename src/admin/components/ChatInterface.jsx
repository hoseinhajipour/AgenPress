import { useState, useRef, useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { sendMessage, confirmAction, orchestrateChat } from '../api';
import FileUpload from './FileUpload';
import ConfirmationModal from './ConfirmationModal';

const DEFAULT_SUGGESTIONS = {
	admin: [
		__( 'What posts do I have on my site?', 'agenpress' ),
		__( 'Write an SEO-optimized blog post about [topic]', 'agenpress' ),
		__( 'Generate meta title and description for my latest post', 'agenpress' ),
	],
	elementor: [
		__( 'Help me design a hero section', 'agenpress' ),
		__( 'Suggest a landing page structure', 'agenpress' ),
	],
	sales: [
		__( 'What are our best-selling products?', 'agenpress' ),
		__( 'Draft a customer support response', 'agenpress' ),
	],
};

export default function ChatInterface( { module = 'admin', orchestrate = false } ) {
	const [ messages, setMessages ] = useState( [] );
	const [ input, setInput ] = useState( '' );
	const [ loading, setLoading ] = useState( false );
	const [ conversationId, setConversationId ] = useState( 0 );
	const [ attachments, setAttachments ] = useState( [] );
	const [ error, setError ] = useState( null );
	const [ pendingAction, setPendingAction ] = useState( null );
	const [ confirming, setConfirming ] = useState( false );
	const messagesEndRef = useRef( null );

	const suggestions = useMemo( () => {
		const modules = window.agenpressData?.modules || [];
		const mod = modules.find( ( m ) => m.id === module );
		return mod?.suggestions?.length ? mod.suggestions : ( DEFAULT_SUGGESTIONS[ module ] || DEFAULT_SUGGESTIONS.admin );
	}, [ module ] );

	useEffect( () => {
		messagesEndRef.current?.scrollIntoView( { behavior: 'smooth' } );
	}, [ messages, pendingAction ] );

	const handleResponse = ( response ) => {
		if ( response.conversation_id ) {
			setConversationId( response.conversation_id );
		}

		if ( response.message ) {
			const prefix = response.specialist
				? `[${ response.specialist }] `
				: '';
			setMessages( ( prev ) => [
				...prev,
				{
					role: 'assistant',
					content: prefix + response.message.content,
				},
			] );
		}

		if ( response.pending_actions?.length > 0 ) {
			setPendingAction( response.pending_actions[ 0 ] );
		}
	};

	const handleSend = async ( text = input ) => {
		if ( ! text.trim() || loading ) return;

		const userMessage = {
			role: 'user',
			content: text.trim(),
			attachments: [ ...attachments ],
		};

		setMessages( ( prev ) => [ ...prev, userMessage ] );
		setInput( '' );
		setLoading( true );
		setError( null );
		setPendingAction( null );

		try {
			const response = orchestrate && module === 'admin'
				? await orchestrateChat( text.trim(), conversationId )
				: await sendMessage( module, text.trim(), conversationId, attachments );
			handleResponse( response );
			setAttachments( [] );
		} catch ( err ) {
			setError( err.message || __( 'Failed to send message.', 'agenpress' ) );
		} finally {
			setLoading( false );
		}
	};

	const handleConfirm = async () => {
		if ( ! pendingAction || ! conversationId ) return;

		setConfirming( true );
		setError( null );

		try {
			const response = await confirmAction(
				module,
				pendingAction.id,
				conversationId
			);
			handleResponse( response );
			setPendingAction( null );
		} catch ( err ) {
			setError( err.message || __( 'Failed to confirm action.', 'agenpress' ) );
		} finally {
			setConfirming( false );
		}
	};

	const handleKeyDown = ( e ) => {
		if ( e.key === 'Enter' && ! e.shiftKey ) {
			e.preventDefault();
			handleSend();
		}
	};

	const handleFileUploaded = ( file ) => {
		setAttachments( ( prev ) => [ ...prev, file ] );
	};

	return (
		<div className="ap-chat-container">
			<ConfirmationModal
				pending={ pendingAction }
				onConfirm={ handleConfirm }
				onCancel={ () => setPendingAction( null ) }
				loading={ confirming }
			/>

			{ error && (
				<div className="ap-alert ap-alert-error">{ error }</div>
			) }

			<div className="ap-chat-messages">
				{ messages.length === 0 && (
					<div className="ap-empty-state">
						<p style={ { fontSize: '16px', marginBottom: '16px' } }>
							{ __( 'How can I help you manage your site?', 'agenpress' ) }
						</p>
						<div className="ap-suggestions">
							{ suggestions.map( ( s ) => (
								<button
									key={ s }
									className="ap-suggestion-chip"
									onClick={ () => handleSend( s ) }
								>
									{ s }
								</button>
							) ) }
						</div>
					</div>
				) }

				{ messages.map( ( msg, i ) => (
					<div key={ i } className={ `ap-message ${ msg.role }` }>
						<div className="ap-message-avatar">
							{ msg.role === 'user' ? 'U' : 'AI' }
						</div>
						<div className="ap-message-bubble">
							{ msg.content }
							{ msg.attachments?.length > 0 && (
								<div style={ { marginTop: '8px', fontSize: '11px', opacity: 0.7 } }>
									📎 { msg.attachments.map( ( a ) => a.name ).join( ', ' ) }
								</div>
							) }
						</div>
					</div>
				) ) }

				{ loading && (
					<div className="ap-message assistant">
						<div className="ap-message-avatar">AI</div>
						<div className="ap-message-bubble">
							<span style={ { opacity: 0.6 } }>{ __( 'Thinking...', 'agenpress' ) }</span>
						</div>
					</div>
				) }

				<div ref={ messagesEndRef } />
			</div>

			<div className="ap-chat-input-area">
				{ attachments.length > 0 && (
					<div style={ { marginBottom: '8px', fontSize: '12px', color: '#64748b' } }>
						📎 { attachments.map( ( a ) => a.name ).join( ', ' ) }
					</div>
				) }
				<div className="ap-chat-input-row">
					<FileUpload onUploaded={ handleFileUploaded } />
					<textarea
						className="ap-chat-input"
						value={ input }
						onChange={ ( e ) => setInput( e.target.value ) }
						onKeyDown={ handleKeyDown }
						placeholder={ __( 'Ask AgenPress anything...', 'agenpress' ) }
						rows={ 1 }
						disabled={ loading }
					/>
					<button
						className="ap-btn ap-btn-primary"
						onClick={ () => handleSend() }
						disabled={ loading || ! input.trim() }
					>
						{ __( 'Send', 'agenpress' ) }
					</button>
				</div>
			</div>
		</div>
	);
}
