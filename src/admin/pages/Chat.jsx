import { useState, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ChatInterface from '../components/ChatInterface';

export default function Chat() {
	const modules = useMemo( () => {
		return window.agenpressData?.modules || [
			{ id: 'admin', name: 'Admin AI', suggestions: [] },
		];
	}, [] );

	const [ activeModule, setActiveModule ] = useState( modules[ 0 ]?.id || 'admin' );
	const [ orchestrate, setOrchestrate ] = useState( false );
	const isEnterprise = window.agenpressData?.licenseTier === 'enterprise';

	return (
		<div>
			<div style={ { display: 'flex', gap: '8px', marginBottom: '16px', flexWrap: 'wrap', alignItems: 'center' } }>
				{ modules.map( ( mod ) => (
					<button
						key={ mod.id }
						className={ `ap-btn ${ activeModule === mod.id ? 'ap-btn-primary' : 'ap-btn-secondary' }` }
						onClick={ () => setActiveModule( mod.id ) }
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
			<div className="ap-card" style={ { padding: 0, overflow: 'hidden' } }>
				<ChatInterface key={ `${ activeModule }-${ orchestrate }` } module={ activeModule } orchestrate={ orchestrate } />
			</div>
		</div>
	);
}
