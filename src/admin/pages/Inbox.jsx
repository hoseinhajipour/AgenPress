import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getInbox, getInboxConversation, resolveInboxConversation, assignInboxConversation, getInboxTeam } from '../api';

export default function Inbox() {
	const [ conversations, setConversations ] = useState( [] );
	const [ selected, setSelected ] = useState( null );
	const [ team, setTeam ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	const loadInbox = useCallback( async () => {
		setLoading( true );
		try {
			const data = await getInbox();
			setConversations( data.conversations || [] );
		} catch ( err ) {
			setError( err.message );
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		loadInbox();
		getInboxTeam().then( ( d ) => setTeam( d.team || [] ) ).catch( () => {} );
	}, [ loadInbox ] );

	const openConversation = async ( id ) => {
		try {
			const data = await getInboxConversation( id );
			setSelected( data );
		} catch ( err ) {
			setError( err.message );
		}
	};

	const handleResolve = async ( id ) => {
		try {
			await resolveInboxConversation( id );
			setSelected( null );
			await loadInbox();
		} catch ( err ) {
			setError( err.message );
		}
	};

	return (
		<div>
			{ error && <div className="ap-alert ap-alert-error">{ error }</div> }

			<div style={ { display: 'grid', gridTemplateColumns: '320px 1fr', gap: '16px', minHeight: '400px' } }>
				<div className="ap-card">
					<h3 style={ { margin: '0 0 12px', fontSize: '16px' } }>
						{ __( 'Escalated Chats', 'agenpress' ) }
					</h3>
					{ loading ? (
						<p className="ap-empty-state">{ __( 'Loading...', 'agenpress' ) }</p>
					) : conversations.length === 0 ? (
						<p className="ap-empty-state">{ __( 'No escalated conversations.', 'agenpress' ) }</p>
					) : (
						<ul style={ { listStyle: 'none', margin: 0, padding: 0 } }>
							{ conversations.map( ( conv ) => (
								<li key={ conv.id }>
									<button
										className={ `ap-btn ${ selected?.id === conv.id ? 'ap-btn-primary' : 'ap-btn-secondary' }` }
										style={ { width: '100%', marginBottom: '8px', textAlign: 'left' } }
										onClick={ () => openConversation( conv.id ) }
									>
										<strong>{ conv.title || __( 'Sales chat', 'agenpress' ) }</strong>
										<br />
										<small>{ conv.updated_at }</small>
									</button>
								</li>
							) ) }
						</ul>
					) }
				</div>

				<div className="ap-card">
					{ ! selected ? (
						<p className="ap-empty-state">{ __( 'Select a conversation to view.', 'agenpress' ) }</p>
					) : (
						<>
							<div style={ { display: 'flex', justifyContent: 'space-between', marginBottom: '16px', gap: '8px', flexWrap: 'wrap' } }>
								<h3 style={ { margin: 0 } }>{ selected.title }</h3>
								<div style={ { display: 'flex', gap: '8px', alignItems: 'center' } }>
									{ team.length > 0 && (
										<select
											className="ap-form-select"
											style={ { fontSize: '13px' } }
											value={ selected.metadata?.assigned_to || '' }
											onChange={ async ( e ) => {
												await assignInboxConversation( selected.id, parseInt( e.target.value, 10 ) || 0 );
												await openConversation( selected.id );
											} }
										>
											<option value="">{ __( 'Unassigned', 'agenpress' ) }</option>
											{ team.map( ( member ) => (
												<option key={ member.id } value={ member.id }>{ member.name }</option>
											) ) }
										</select>
									) }
									<button
										className="ap-btn ap-btn-primary"
										onClick={ () => handleResolve( selected.id ) }
									>
										{ __( 'Mark Resolved', 'agenpress' ) }
									</button>
								</div>
							</div>
							{ selected.metadata?.escalation_reason && (
								<p style={ { color: '#646970', fontSize: '13px' } }>
									<strong>{ __( 'Reason:', 'agenpress' ) }</strong> { selected.metadata.escalation_reason }
								</p>
							) }
							<div style={ { display: 'flex', flexDirection: 'column', gap: '8px' } }>
								{ ( selected.messages || [] ).map( ( msg ) => (
									<div
										key={ msg.id }
										style={ {
											padding: '8px 12px',
											borderRadius: '6px',
											background: msg.role === 'user' ? '#f0f0f1' : '#e8f4fd',
											alignSelf: msg.role === 'user' ? 'flex-end' : 'flex-start',
											maxWidth: '80%',
										} }
									>
										<strong style={ { fontSize: '11px', textTransform: 'uppercase' } }>
											{ msg.role }
										</strong>
										<p style={ { margin: '4px 0 0', whiteSpace: 'pre-wrap' } }>{ msg.content }</p>
									</div>
								) ) }
							</div>
						</>
					) }
				</div>
			</div>
		</div>
	);
}
