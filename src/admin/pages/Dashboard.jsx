import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getTasks, getMemory } from '../api';

export default function Dashboard( { onNavigate } ) {
	const [ stats, setStats ] = useState( {
		tasks: 0,
		running: 0,
		memory: 0,
	} );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		async function load() {
			try {
				const [ tasks, memoryData ] = await Promise.all( [
					getTasks(),
					getMemory(),
				] );

				setStats( {
					tasks: tasks.length,
					running: tasks.filter( ( t ) => t.status === 'running' ).length,
					memory: memoryData.entries?.length || 0,
				} );
			} catch {
				// Stats unavailable without active WP context.
			} finally {
				setLoading( false );
			}
		}
		load();
	}, [] );

	const cards = [
		{
			label: __( 'Total Tasks', 'agenpress' ),
			value: loading ? '...' : stats.tasks,
			action: () => onNavigate( 'tasks' ),
		},
		{
			label: __( 'Running Agents', 'agenpress' ),
			value: loading ? '...' : stats.running,
			action: () => onNavigate( 'tasks' ),
		},
		{
			label: __( 'Memory Entries', 'agenpress' ),
			value: loading ? '...' : stats.memory,
			action: () => onNavigate( 'memory' ),
		},
	];

	return (
		<div>
			<div className="ap-stat-grid" style={ { marginBottom: '24px' } }>
				{ cards.map( ( card ) => (
					<div
						key={ card.label }
						className="ap-stat-card"
						style={ { cursor: 'pointer' } }
						onClick={ card.action }
						role="button"
						tabIndex={ 0 }
						onKeyDown={ ( e ) => e.key === 'Enter' && card.action() }
					>
						<h3>{ card.label }</h3>
						<p>{ card.value }</p>
					</div>
				) ) }
			</div>

			<div className="ap-card">
				<h3 style={ { margin: '0 0 12px', fontSize: '16px' } }>
					{ __( 'Quick Actions', 'agenpress' ) }
				</h3>
				<div style={ { display: 'flex', gap: '8px', flexWrap: 'wrap' } }>
					<button className="ap-btn ap-btn-primary" onClick={ () => onNavigate( 'chat' ) }>
						{ __( 'Start AI Chat', 'agenpress' ) }
					</button>
					<button className="ap-btn ap-btn-secondary" onClick={ () => onNavigate( 'tasks' ) }>
						{ __( 'View Tasks', 'agenpress' ) }
					</button>
					<button className="ap-btn ap-btn-secondary" onClick={ () => onNavigate( 'memory' ) }>
						{ __( 'Manage Memory', 'agenpress' ) }
					</button>
					<button className="ap-btn ap-btn-secondary" onClick={ () => onNavigate( 'settings' ) }>
						{ __( 'Configure AI', 'agenpress' ) }
					</button>
				</div>
			</div>

			<div className="ap-card" style={ { marginTop: '16px' } }>
				<h3 style={ { margin: '0 0 8px', fontSize: '16px' } }>
					{ __( 'About AgenPress', 'agenpress' ) }
				</h3>
				<p style={ { margin: 0, fontSize: '14px', color: '#64748b', lineHeight: 1.6 } }>
					{ __( 'AgenPress is your AI Operating System for WordPress. Use the Admin AI to manage content, Agent Tasks for multi-step automation, and Memory to store brand knowledge for smarter responses.', 'agenpress' ) }
				</p>
			</div>
		</div>
	);
}
