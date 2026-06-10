import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Sidebar from './components/Sidebar';
import Dashboard from './pages/Dashboard';
import Chat from './pages/Chat';
import Tasks from './pages/Tasks';
import Memory from './pages/Memory';
import Settings from './pages/Settings';
import SalesChatSettings from './pages/SalesChatSettings';
import Inbox from './pages/Inbox';
import Analytics from './pages/Analytics';
import Workflows from './pages/Workflows';

const PAGES = {
	dashboard: Dashboard,
	chat: Chat,
	inbox: Inbox,
	analytics: Analytics,
	workflows: Workflows,
	tasks: Tasks,
	memory: Memory,
	settings: Settings,
	'sales-chat': SalesChatSettings,
};

const PAGE_TITLES = {
	dashboard: __( 'Dashboard', 'agenpress' ),
	chat: __( 'AI Chat', 'agenpress' ),
	inbox: __( 'Sales Inbox', 'agenpress' ),
	analytics: __( 'Analytics', 'agenpress' ),
	workflows: __( 'Workflows', 'agenpress' ),
	tasks: __( 'Agent Tasks', 'agenpress' ),
	memory: __( 'Memory Manager', 'agenpress' ),
	settings: __( 'Settings', 'agenpress' ),
	'sales-chat': __( 'Storefront Sales Chat', 'agenpress' ),
};

export default function App() {
	const [ page, setPage ] = useState( 'dashboard' );
	const PageComponent = PAGES[ page ] || Dashboard;

	return (
		<div className="ap-app">
			<Sidebar currentPage={ page } onNavigate={ setPage } />
			<div className="ap-main">
				<div className="ap-header">
					<h2>{ PAGE_TITLES[ page ] }</h2>
				</div>
				<div className="ap-content">
					<PageComponent onNavigate={ setPage } />
				</div>
			</div>
		</div>
	);
}
