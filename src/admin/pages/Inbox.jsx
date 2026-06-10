import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getInbox, getInboxConversation, resolveInboxConversation, assignInboxConversation, getInboxTeam } from '../api';

const TABS = [
	{ id: 'all', label: __( 'All', 'agenpress' ) },
	{ id: 'active', label: __( 'Active', 'agenpress' ) },
	{ id: 'escalated', label: __( 'Escalated', 'agenpress' ) },
	{ id: 'resolved', label: __( 'Resolved', 'agenpress' ) },
];

const STATUS_LABELS = {
	active: __( 'Active', 'agenpress' ),
	escalated: __( 'Escalated', 'agenpress' ),
	resolved: __( 'Resolved', 'agenpress' ),
};

const ROLE_LABELS = {
	user: __( 'Customer', 'agenpress' ),
	assistant: __( 'Assistant', 'agenpress' ),
};

function StatusBadge( { status } ) {
	const label = STATUS_LABELS[ status ] || status;

	return <span className={ `ap-inbox-status ap-inbox-status-${ status }` }>{ label }</span>;
}

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

export default function Inbox() {
	const [ tab, setTab ] = useState( 'all' );
	const [ conversations, setConversations ] = useState( [] );
	const [ counts, setCounts ] = useState( { all: 0, active: 0, escalated: 0, resolved: 0 } );
	const [ selected, setSelected ] = useState( null );
	const [ team, setTeam ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	const loadInbox = useCallback( async () => {
		setLoading( true );
		setError( null );

		try {
			const data = await getInbox( tab === 'all' ? '' : tab );
			setConversations( data.conversations || [] );
			setCounts( data.counts || { all: 0, active: 0, escalated: 0, resolved: 0 } );
		} catch ( err ) {
			setError( err.message );
		} finally {
			setLoading( false );
		}
	}, [ tab ] );

	useEffect( () => {
		loadInbox();
		getInboxTeam().then( ( d ) => setTeam( d.team || [] ) ).catch( () => {} );
	}, [ loadInbox ] );

	useEffect( () => {
		setSelected( null );
	}, [ tab ] );

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

	const emptyMessage = {
		all: __( 'No storefront sales chats yet.', 'agenpress' ),
		active: __( 'No active sales chats.', 'agenpress' ),
		escalated: __( 'No escalated conversations.', 'agenpress' ),
		resolved: __( 'No resolved conversations.', 'agenpress' ),
	};

	return (
		<div className="ap-inbox">
			{ error && <div className="ap-alert ap-alert-error">{ error }</div> }

			<p className="ap-inbox-intro">
				{ __( 'All AI sales chats from the storefront widget. Escalated chats need human follow-up.', 'agenpress' ) }
			</p>

			<div className="ap-inbox-tabs" role="tablist">
				{ TABS.map( ( item ) => (
					<button
						key={ item.id }
						type="button"
						role="tab"
						aria-selected={ tab === item.id }
						className={ `ap-inbox-tab ${ tab === item.id ? 'active' : '' }` }
						onClick={ () => setTab( item.id ) }
					>
						<span>{ item.label }</span>
						<span className="ap-inbox-tab-count">{ counts[ item.id ] ?? 0 }</span>
					</button>
				) ) }
			</div>

			<div className="ap-inbox-layout">
				<div className="ap-card ap-inbox-list">
					<h3 className="ap-inbox-list-title">
						{ __( 'Storefront Sales Chats', 'agenpress' ) }
					</h3>
					{ loading ? (
						<p className="ap-empty-state">{ __( 'Loading...', 'agenpress' ) }</p>
					) : conversations.length === 0 ? (
						<p className="ap-empty-state">{ emptyMessage[ tab ] }</p>
					) : (
						<ul className="ap-inbox-conversations">
							{ conversations.map( ( conv ) => (
								<li key={ conv.id }>
									<button
										type="button"
										className={ `ap-inbox-conversation-btn ${ selected?.id === conv.id ? 'is-selected' : '' }` }
										onClick={ () => openConversation( conv.id ) }
									>
										<div className="ap-inbox-conversation-top">
											<strong>{ conv.title || __( 'Sales chat', 'agenpress' ) }</strong>
											<StatusBadge status={ conv.status } />
										</div>
										<small>{ formatDate( conv.updated_at ) }</small>
										{ conv.user_id > 0 && (
											<small className="ap-inbox-meta">
												{ __( 'Logged-in customer', 'agenpress' ) } #{ conv.user_id }
											</small>
										) }
									</button>
								</li>
							) ) }
						</ul>
					) }
				</div>

				<div className="ap-card ap-inbox-detail">
					{ ! selected ? (
						<p className="ap-empty-state">{ __( 'Select a conversation to view.', 'agenpress' ) }</p>
					) : (
						<>
							<div className="ap-inbox-detail-header">
								<div>
									<h3>{ selected.title || __( 'Sales chat', 'agenpress' ) }</h3>
									<StatusBadge status={ selected.status } />
								</div>
								<div className="ap-inbox-detail-actions">
									{ team.length > 0 && selected.status === 'escalated' && (
										<select
											className="ap-form-select"
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
									{ selected.status === 'escalated' && (
										<button
											type="button"
											className="ap-btn ap-btn-primary"
											onClick={ () => handleResolve( selected.id ) }
										>
											{ __( 'Mark Resolved', 'agenpress' ) }
										</button>
									) }
								</div>
							</div>

							{ selected.metadata?.escalation_reason && (
								<p className="ap-inbox-reason">
									<strong>{ __( 'Reason:', 'agenpress' ) }</strong> { selected.metadata.escalation_reason }
								</p>
							) }

							<div className="ap-inbox-messages">
								{ ( selected.messages || [] )
									.filter( ( msg ) => [ 'user', 'assistant' ].includes( msg.role ) )
									.map( ( msg ) => (
										<div
											key={ msg.id }
											className={ `ap-inbox-message ap-inbox-message-${ msg.role }` }
										>
											<strong className="ap-inbox-message-role">
												{ ROLE_LABELS[ msg.role ] || msg.role }
											</strong>
											<p>{ msg.content }</p>
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
