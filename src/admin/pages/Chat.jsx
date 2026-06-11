import { useState, useMemo, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ChatInterface from '../components/ChatInterface';
import ChatHistorySidebar from '../components/ChatHistorySidebar';
import { getConversation } from '../api';

const mapConversationMessages = ( messages = [] ) => {
	return messages
		.filter( ( msg ) => [ 'user', 'assistant' ].includes( msg.role ) )
		.map( ( msg ) => ( {
			id: msg.id,
			role: msg.role,
			content: msg.content,
			attachments: msg.attachments || [],
		} ) );
};

export default function Chat( { onNavigate } ) {
	const modules = useMemo( () => {
		return window.agenpressData?.modules || [
			{ id: 'admin', name: 'Admin AI', suggestions: [] },
		];
	}, [] );

	const [ activeModule, setActiveModule ] = useState( modules[ 0 ]?.id || 'admin' );
	const [ orchestrate, setOrchestrate ] = useState( false );
	const [ activeConversationId, setActiveConversationId ] = useState( 0 );
	const [ initialMessages, setInitialMessages ] = useState( [] );
	const [ chatKey, setChatKey ] = useState( 0 );
	const [ listRefreshKey, setListRefreshKey ] = useState( 0 );
	const [ loadError, setLoadError ] = useState( null );
	const isEnterprise = window.agenpressData?.licenseTier === 'enterprise';

	const startNewChat = useCallback( () => {
		setActiveConversationId( 0 );
		setInitialMessages( [] );
		setLoadError( null );
		setChatKey( ( key ) => key + 1 );
	}, [] );

	const handleModuleChange = ( moduleId ) => {
		setActiveModule( moduleId );
		startNewChat();
	};

	const handleSelectConversation = async ( id ) => {
		if ( id === activeConversationId ) {
			return;
		}

		setLoadError( null );

		try {
			const data = await getConversation( id );
			setActiveConversationId( id );
			setInitialMessages( mapConversationMessages( data.messages ) );
			setChatKey( ( key ) => key + 1 );
		} catch ( err ) {
			setLoadError( err.message || __( 'Failed to load conversation.', 'agenpress' ) );
		}
	};

	const handleConversationChange = useCallback( ( id ) => {
		setActiveConversationId( id );
		setListRefreshKey( ( key ) => key + 1 );
	}, [] );

	const handleConversationDeleted = useCallback( ( id ) => {
		if ( id === activeConversationId ) {
			startNewChat();
		}
	}, [ activeConversationId, startNewChat ] );

	const handleChatCleared = useCallback( () => {
		startNewChat();
		setListRefreshKey( ( key ) => key + 1 );
	}, [ startNewChat ] );

	return (
		<div>
			<div style={ { display: 'flex', gap: '8px', marginBottom: '16px', flexWrap: 'wrap', alignItems: 'center' } }>
				{ modules.map( ( mod ) => (
					<button
						key={ mod.id }
						className={ `ap-btn ${ activeModule === mod.id ? 'ap-btn-primary' : 'ap-btn-secondary' }` }
						onClick={ () => handleModuleChange( mod.id ) }
					>
						{ mod.name }
					</button>
				) ) }
				{ isEnterprise && activeModule === 'admin' && (
					<label style={ { marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: '6px', fontSize: '13px' } }>
						<input type="checkbox" checked={ orchestrate } onChange={ ( e ) => setOrchestrate( e.target.checked ) } />
						{ __( 'Multi-Agent', 'agenpress' ) }
					</label>
				) }
			</div>

			{ loadError && (
				<div className="ap-alert ap-alert-error" style={ { marginBottom: '12px' } }>{ loadError }</div>
			) }

			<div className="ap-chat-layout">
				<ChatHistorySidebar
					module={ activeModule }
					selectedId={ activeConversationId }
					refreshKey={ listRefreshKey }
					onSelect={ handleSelectConversation }
					onNewChat={ startNewChat }
					onDeleted={ handleConversationDeleted }
				/>
				<div className="ap-card ap-chat-main" style={ { padding: 0, overflow: 'hidden' } }>
					<ChatInterface
						key={ `${ activeModule }-${ orchestrate }-${ chatKey }` }
						module={ activeModule }
						orchestrate={ orchestrate }
						initialConversationId={ activeConversationId }
						initialMessages={ initialMessages }
						onConversationChange={ handleConversationChange }
						onChatCleared={ handleChatCleared }
						onNavigateToTasks={ onNavigate ? () => onNavigate( 'tasks' ) : null }
					/>
				</div>
			</div>
		</div>
	);
}
