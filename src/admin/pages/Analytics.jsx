import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getAnalytics } from '../api';

export default function Analytics() {
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		getAnalytics()
			.then( setData )
			.catch( ( err ) => setError( err.message ) )
			.finally( () => setLoading( false ) );
	}, [] );

	if ( loading ) {
		return <p className="ap-empty-state">{ __( 'Loading analytics...', 'agenpress' ) }</p>;
	}

	if ( error ) {
		return <div className="ap-alert ap-alert-error">{ error }</div>;
	}

	const cards = [
		{ label: __( 'Tokens Used', 'agenpress' ), value: data.tokens_used },
		{ label: __( 'Messages', 'agenpress' ), value: data.messages },
		{ label: __( 'Conversations', 'agenpress' ), value: data.conversations },
		{ label: __( 'Tasks Completed', 'agenpress' ), value: data.tasks_completed },
		{ label: __( 'Tool Executions', 'agenpress' ), value: data.tool_executions },
	];

	return (
		<div>
			<p style={ { color: '#64748b', marginBottom: '16px' } }>
				{ __( 'Last', 'agenpress' ) } { data.period_days } { __( 'days', 'agenpress' ) }
			</p>
			<div className="ap-stat-grid" style={ { marginBottom: '24px' } }>
				{ cards.map( ( card ) => (
					<div key={ card.label } className="ap-stat-card">
						<h3>{ card.label }</h3>
						<p>{ card.value }</p>
					</div>
				) ) }
			</div>

			<div className="ap-card" style={ { marginBottom: '16px' } }>
				<h3 style={ { margin: '0 0 12px', fontSize: '16px' } }>
					{ __( 'Conversations by Module', 'agenpress' ) }
				</h3>
				{ Object.entries( data.by_module || {} ).map( ( [ mod, count ] ) => (
					<div key={ mod } style={ { display: 'flex', justifyContent: 'space-between', padding: '6px 0', borderBottom: '1px solid #e2e8f0' } }>
						<span>{ mod }</span>
						<strong>{ count }</strong>
					</div>
				) ) }
			</div>

			<div className="ap-card">
				<h3 style={ { margin: '0 0 12px', fontSize: '16px' } }>
					{ __( 'Daily Messages', 'agenpress' ) }
				</h3>
				{ ( data.daily_messages || [] ).map( ( day ) => (
					<div key={ day.date } style={ { display: 'flex', justifyContent: 'space-between', padding: '4px 0', fontSize: '13px' } }>
						<span>{ day.date }</span>
						<span>{ day.count }</span>
					</div>
				) ) }
			</div>
		</div>
	);
}
