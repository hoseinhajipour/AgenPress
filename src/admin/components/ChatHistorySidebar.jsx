import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getConversations, deleteConversation } from '../api';

function formatDate( value ) {
	if ( ! value ) {
		return '';
	}

	try {
		return new Date( value ).toLocaleString();
	} catch {
		return value;
	}
}

function conversationLabel( conv ) {
	const title = ( conv.title || '' ).trim();

	if ( title ) {
		return title;
	}

	return __( 'Untitled chat', 'agenpress' );
}

export default function ChatHistorySidebar( {
	module,
	selectedId = 0,
	refreshKey = 0,
	onSelect,
	onNewChat,
	onDeleted,
} ) {
	const [ conversations, setConversations ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ deletingId, setDeletingId ] = useState( 0 );

	const loadConversations = useCallback( async () => {
		setLoading( true );
		setError( null );

		try {
			const data = await getConversations( module );
			setConversations( Array.isArray( data ) ? data : [] );
		} catch ( err ) {
			setError( err.message || __( 'Failed to load conversations.', 'agenpress' ) );
		} finally {
			setLoading( false );
		}
	}, [ module ] );

	useEffect( () => {
		loadConversations();
	}, [ loadConversations, refreshKey ] );

	const handleDelete = async ( event, id ) => {
		event.stopPropagation();

		if ( ! window.confirm( __( 'Delete this conversation?', 'agenpress' ) ) ) {
			return;
		}

		setDeletingId( id );
		setError( null );

		try {
			await deleteConversation( id );
			setConversations( ( prev ) => prev.filter( ( conv ) => conv.id !== id ) );

			if ( onDeleted ) {
				onDeleted( id );
			}
		} catch ( err ) {
			setError( err.message || __( 'Failed to delete conversation.', 'agenpress' ) );
		} finally {
			setDeletingId( 0 );
		}
	};

	return (
		<aside className="ap-chat-history">
			<div className="ap-chat-history-header">
				<h3 className="ap-chat-history-title">{ __( 'Conversations', 'agenpress' ) }</h3>
				<button
					type="button"
					className="ap-btn ap-btn-primary ap-chat-history-new"
					onClick={ onNewChat }
				>
					{ __( 'New chat', 'agenpress' ) }
				</button>
			</div>

			{ error && (
				<div className="ap-alert ap-alert-error ap-chat-history-error">{ error }</div>
			) }

			<div className="ap-chat-history-list">
				{ loading ? (
					<p className="ap-empty-state ap-chat-history-empty">{ __( 'Loading...', 'agenpress' ) }</p>
				) : conversations.length === 0 ? (
					<p className="ap-empty-state ap-chat-history-empty">
						{ __( 'No previous chats yet.', 'agenpress' ) }
					</p>
				) : (
					<ul className="ap-chat-history-items">
						{ conversations.map( ( conv ) => (
							<li
								key={ conv.id }
								className={ `ap-chat-history-item ${ selectedId === conv.id ? 'is-selected' : '' }` }
							>
								<button
									type="button"
									className="ap-chat-history-item-btn"
									onClick={ () => onSelect( conv.id ) }
									disabled={ deletingId === conv.id }
								>
									<span className="ap-chat-history-item-title">
										{ conversationLabel( conv ) }
									</span>
									<small className="ap-chat-history-item-date">
										{ formatDate( conv.updated_at || conv.created_at ) }
									</small>
								</button>
								<button
									type="button"
									className="ap-chat-history-delete"
									onClick={ ( event ) => handleDelete( event, conv.id ) }
									disabled={ deletingId === conv.id }
									aria-label={ __( 'Delete conversation', 'agenpress' ) }
									title={ __( 'Delete conversation', 'agenpress' ) }
								>
									×
								</button>
							</li>
						) ) }
					</ul>
				) }
			</div>
		</aside>
	);
}
