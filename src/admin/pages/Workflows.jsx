import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getWorkflows, createWorkflow, runWorkflow, deleteWorkflow } from '../api';

const isEnterprise = window.agenpressData?.licenseTier === 'enterprise';

const DEFAULT_STEPS = [
	{ type: 'ai', prompt: 'Summarize the site content strategy as JSON bullet points.', output_key: 'summary' },
];

export default function Workflows() {
	const [ workflows, setWorkflows ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ title, setTitle ] = useState( '' );

	const load = useCallback( async () => {
		try {
			const data = await getWorkflows();
			setWorkflows( data.workflows || [] );
		} catch ( err ) {
			setError( err.message );
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		if ( ! isEnterprise ) {
			setLoading( false );
			return;
		}

		load();
	}, [ load ] );

	if ( ! isEnterprise ) {
		return (
			<div className="ap-alert ap-alert-error">
				{ __( 'Workflows require an Enterprise license. Update the license tier in Settings to enable automation workflows.', 'agenpress' ) }
			</div>
		);
	}

	const handleCreate = async ( e ) => {
		e.preventDefault();
		if ( ! title.trim() ) return;

		try {
			await createWorkflow( title.trim(), DEFAULT_STEPS );
			setTitle( '' );
			await load();
		} catch ( err ) {
			setError( err.message );
		}
	};

	const handleRun = async ( id ) => {
		try {
			await runWorkflow( id );
			await load();
		} catch ( err ) {
			setError( err.message );
		}
	};

	const handleDelete = async ( id ) => {
		try {
			await deleteWorkflow( id );
			await load();
		} catch ( err ) {
			setError( err.message );
		}
	};

	return (
		<div>
			{ error && <div className="ap-alert ap-alert-error">{ error }</div> }

			<div className="ap-card" style={ { marginBottom: '16px' } }>
				<h3 style={ { margin: '0 0 12px', fontSize: '16px' } }>
					{ __( 'Create Workflow', 'agenpress' ) }
				</h3>
				<form onSubmit={ handleCreate } style={ { display: 'flex', gap: '8px' } }>
					<input
						className="ap-form-input"
						value={ title }
						onChange={ ( e ) => setTitle( e.target.value ) }
						placeholder={ __( 'Workflow name', 'agenpress' ) }
					/>
					<button className="ap-btn ap-btn-primary" type="submit">
						{ __( 'Create', 'agenpress' ) }
					</button>
				</form>
			</div>

			<div className="ap-card">
				<h3 style={ { margin: '0 0 12px', fontSize: '16px' } }>
					{ __( 'Workflows', 'agenpress' ) }
				</h3>
				{ loading ? (
					<p className="ap-empty-state">{ __( 'Loading...', 'agenpress' ) }</p>
				) : workflows.length === 0 ? (
					<p className="ap-empty-state">{ __( 'No workflows yet.', 'agenpress' ) }</p>
				) : (
					<table className="ap-table">
						<thead>
							<tr>
								<th>{ __( 'Title', 'agenpress' ) }</th>
								<th>{ __( 'Trigger', 'agenpress' ) }</th>
								<th>{ __( 'Steps', 'agenpress' ) }</th>
								<th>{ __( 'Last Run', 'agenpress' ) }</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							{ workflows.map( ( wf ) => (
								<tr key={ wf.id }>
									<td><strong>{ wf.title }</strong></td>
									<td>{ wf.trigger_type }</td>
									<td>{ ( wf.steps || [] ).length }</td>
									<td>{ wf.last_run_at || '—' }</td>
									<td>
										<button className="ap-btn ap-btn-primary" style={ { marginRight: '4px', padding: '4px 8px', fontSize: '12px' } } onClick={ () => handleRun( wf.id ) }>
											{ __( 'Run', 'agenpress' ) }
										</button>
										<button className="ap-btn ap-btn-danger" style={ { padding: '4px 8px', fontSize: '12px' } } onClick={ () => handleDelete( wf.id ) }>
											{ __( 'Delete', 'agenpress' ) }
										</button>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) }
			</div>
		</div>
	);
}
