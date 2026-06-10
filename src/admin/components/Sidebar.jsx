import { __ } from '@wordpress/i18n';

const NAV_ITEMS = [
	{ id: 'dashboard', label: __( 'Dashboard', 'agenpress' ), icon: '📊' },
	{ id: 'chat', label: __( 'AI Chat', 'agenpress' ), icon: '💬' },
	{ id: 'inbox', label: __( 'Sales Inbox', 'agenpress' ), icon: '📥' },
	{ id: 'analytics', label: __( 'Analytics', 'agenpress' ), icon: '📈' },
	{ id: 'workflows', label: __( 'Workflows', 'agenpress' ), icon: '🔁' },
	{ id: 'tasks', label: __( 'Agent Tasks', 'agenpress' ), icon: '⚡' },
	{ id: 'memory', label: __( 'Memory', 'agenpress' ), icon: '🧠' },
	{ id: 'settings', label: __( 'Settings', 'agenpress' ), icon: '⚙️' },
];

export default function Sidebar( { currentPage, onNavigate } ) {
	const { siteName, version, userName } = window.agenpressData || {};

	return (
		<aside className="ap-sidebar">
			<div className="ap-sidebar-logo">
				<h1>AgenPress</h1>
				<p>{ siteName || 'WordPress AI' } · v{ version }</p>
			</div>
			<nav>
				{ NAV_ITEMS.map( ( item ) => (
					<button
						key={ item.id }
						className={ `ap-nav-item ${ currentPage === item.id ? 'active' : '' }` }
						onClick={ () => onNavigate( item.id ) }
					>
						<span>{ item.icon }</span>
						<span>{ item.label }</span>
					</button>
				) ) }
			</nav>
			<div style={ { marginTop: 'auto', padding: '16px 20px', borderTop: '1px solid #334155', fontSize: '12px', color: '#94a3b8' } }>
				{ userName }
			</div>
		</aside>
	);
}
