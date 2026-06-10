import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	getWorkflows,
	createWorkflow,
	updateWorkflow,
	runWorkflow,
	deleteWorkflow,
} from '../api';
import { getWorkflowPresets } from '../workflowPresets';

const isEnterprise = window.agenpressData?.licenseTier === 'enterprise';
const hasWooCommerce = !! window.agenpressData?.woocommerce;

const STEP_TYPES = [
	{ id: 'ai', label: __( 'AI Prompt', 'agenpress' ) },
	{ id: 'tool', label: __( 'Tool Call', 'agenpress' ) },
	{ id: 'ai_plan', label: __( 'AI Planning', 'agenpress' ) },
	{ id: 'seo_article', label: __( 'SEO Article', 'agenpress' ) },
	{ id: 'product_description', label: __( 'Product Description', 'agenpress' ) },
];

const DEFAULT_SEO_OPTIONS = {
	sections_count: 4,
	featured_image: false,
	section_images: false,
	include_faq: true,
	include_conclusion: true,
	suggest_services: false,
	suggest_products: false,
};

function createEmptyStep( type ) {
	const base = { type, label: '' };

	switch ( type ) {
		case 'ai':
			return { ...base, prompt: '', output_key: '' };
		case 'tool':
			return { ...base, tool: 'list_posts', args: { limit: 10 }, output_key: '' };
		case 'ai_plan':
			return { ...base, title: '', description: '' };
		case 'seo_article':
			return {
				...base,
				topic: '',
				index: 0,
				publish: false,
				options: { ...DEFAULT_SEO_OPTIONS },
			};
		case 'product_description':
			return { ...base, niche: '', index: 0 };
		default:
			return { ...base, type: 'ai', prompt: '', output_key: '' };
	}
}

function normalizeStepForEdit( step ) {
	const normalized = { ...step };
	if ( normalized.type === 'tool' && typeof normalized.args !== 'object' ) {
		normalized.args = {};
	}
	if ( normalized.type === 'seo_article' ) {
		normalized.options = { ...DEFAULT_SEO_OPTIONS, ...( normalized.options || {} ) };
	}
	normalized._argsJson = JSON.stringify( normalized.args || {}, null, 2 );
	return normalized;
}

function prepareStepForSave( step ) {
	const saved = { ...step };
	delete saved._argsJson;

	if ( saved.type === 'tool' ) {
		try {
			saved.args = JSON.parse( step._argsJson || '{}' );
		} catch {
			throw new Error( __( 'Invalid JSON in tool arguments.', 'agenpress' ) );
		}
	}

	Object.keys( saved ).forEach( ( key ) => {
		if ( key.startsWith( '_' ) ) {
			delete saved[ key ];
		}
	} );

	return saved;
}

function StepFields( { step, index, onChange } ) {
	const update = ( patch ) => onChange( index, { ...step, ...patch } );

	return (
		<div className="ap-workflow-step-fields">
			<div className="ap-form-group">
				<label className="ap-form-label">{ __( 'Step label', 'agenpress' ) }</label>
				<input
					className="ap-form-input"
					value={ step.label || '' }
					onChange={ ( e ) => update( { label: e.target.value } ) }
					placeholder={ __( 'Optional display label', 'agenpress' ) }
				/>
			</div>

			{ step.type === 'ai' && (
				<>
					<div className="ap-form-group">
						<label className="ap-form-label">{ __( 'Prompt', 'agenpress' ) }</label>
						<textarea
							className="ap-form-textarea"
							rows={ 4 }
							value={ step.prompt || '' }
							onChange={ ( e ) => update( { prompt: e.target.value } ) }
							placeholder={ __( 'Instructions for the AI. Use {{output_key}} to reference prior step outputs.', 'agenpress' ) }
						/>
					</div>
					<div className="ap-form-group">
						<label className="ap-form-label">{ __( 'Output key', 'agenpress' ) }</label>
						<input
							className="ap-form-input"
							value={ step.output_key || '' }
							onChange={ ( e ) => update( { output_key: e.target.value } ) }
							placeholder="summary"
						/>
					</div>
				</>
			) }

			{ step.type === 'tool' && (
				<>
					<div className="ap-form-group">
						<label className="ap-form-label">{ __( 'Tool name', 'agenpress' ) }</label>
						<input
							className="ap-form-input"
							value={ step.tool || '' }
							onChange={ ( e ) => update( { tool: e.target.value } ) }
							placeholder="create_post"
						/>
					</div>
					<div className="ap-form-group">
						<label className="ap-form-label">{ __( 'Tool arguments (JSON)', 'agenpress' ) }</label>
						<textarea
							className="ap-form-textarea"
							rows={ 4 }
							value={ step._argsJson || '{}' }
							onChange={ ( e ) => update( { _argsJson: e.target.value } ) }
						/>
					</div>
					<div className="ap-form-group">
						<label className="ap-form-label">{ __( 'Output key', 'agenpress' ) }</label>
						<input
							className="ap-form-input"
							value={ step.output_key || '' }
							onChange={ ( e ) => update( { output_key: e.target.value } ) }
						/>
					</div>
				</>
			) }

			{ step.type === 'ai_plan' && (
				<>
					<div className="ap-form-group">
						<label className="ap-form-label">{ __( 'Task title', 'agenpress' ) }</label>
						<input
							className="ap-form-input"
							value={ step.title || '' }
							onChange={ ( e ) => update( { title: e.target.value } ) }
						/>
					</div>
					<div className="ap-form-group">
						<label className="ap-form-label">{ __( 'Task description', 'agenpress' ) }</label>
						<textarea
							className="ap-form-textarea"
							rows={ 3 }
							value={ step.description || '' }
							onChange={ ( e ) => update( { description: e.target.value } ) }
						/>
					</div>
				</>
			) }

			{ step.type === 'seo_article' && (
				<>
					<div className="ap-form-group">
						<label className="ap-form-label">{ __( 'Topic', 'agenpress' ) }</label>
						<input
							className="ap-form-input"
							value={ step.topic || '' }
							onChange={ ( e ) => update( { topic: e.target.value } ) }
						/>
					</div>
					<div className="ap-form-group">
						<label className="ap-form-label">{ __( 'Article index', 'agenpress' ) }</label>
						<input
							className="ap-form-input"
							type="number"
							min="0"
							value={ step.index ?? 0 }
							onChange={ ( e ) => update( { index: parseInt( e.target.value, 10 ) || 0 } ) }
						/>
					</div>
					<label className="ap-form-checkbox">
						<input
							type="checkbox"
							checked={ !! step.publish }
							onChange={ ( e ) => update( { publish: e.target.checked } ) }
						/>
						{ __( 'Publish when done', 'agenpress' ) }
					</label>
					<div className="ap-form-group" style={ { marginTop: '12px' } }>
						<label className="ap-form-label">{ __( 'Sections count', 'agenpress' ) }</label>
						<input
							className="ap-form-input"
							type="number"
							min="2"
							max="10"
							value={ step.options?.sections_count ?? 4 }
							onChange={ ( e ) =>
								update( {
									options: {
										...step.options,
										sections_count: parseInt( e.target.value, 10 ) || 4,
									},
								} )
							}
						/>
					</div>
					{ [
						[ 'featured_image', __( 'Generate featured image', 'agenpress' ) ],
						[ 'section_images', __( 'Generate image for each section', 'agenpress' ) ],
						[ 'include_faq', __( 'Include FAQ section', 'agenpress' ) ],
						[ 'include_conclusion', __( 'Include conclusion', 'agenpress' ) ],
						[ 'suggest_services', __( 'Suggest related services', 'agenpress' ) ],
						[ 'suggest_products', __( 'Suggest related products', 'agenpress' ) ],
					].map( ( [ key, label ] ) => (
						<label key={ key } className="ap-form-checkbox">
							<input
								type="checkbox"
								checked={ !! step.options?.[ key ] }
								onChange={ ( e ) =>
									update( {
										options: { ...step.options, [ key ]: e.target.checked },
									} )
								}
							/>
							{ label }
						</label>
					) ) }
				</>
			) }

			{ step.type === 'product_description' && (
				<>
					<div className="ap-form-group">
						<label className="ap-form-label">{ __( 'Product niche', 'agenpress' ) }</label>
						<input
							className="ap-form-input"
							value={ step.niche || '' }
							onChange={ ( e ) => update( { niche: e.target.value } ) }
						/>
					</div>
					<div className="ap-form-group">
						<label className="ap-form-label">{ __( 'Product index', 'agenpress' ) }</label>
						<input
							className="ap-form-input"
							type="number"
							min="0"
							value={ step.index ?? 0 }
							onChange={ ( e ) => update( { index: parseInt( e.target.value, 10 ) || 0 } ) }
						/>
					</div>
				</>
			) }
		</div>
	);
}

function WorkflowEditor( { workflow, onSave, onCancel, saving } ) {
	const [ form, setForm ] = useState( {
		title: workflow.title || '',
		description: workflow.description || '',
		enabled: workflow.enabled !== false,
		trigger_type: workflow.trigger_type || 'manual',
		steps: ( workflow.steps || [] ).map( normalizeStepForEdit ),
	} );
	const [ formError, setFormError ] = useState( null );

	const updateStep = ( index, step ) => {
		setForm( ( prev ) => {
			const steps = [ ...prev.steps ];
			steps[ index ] = step;
			return { ...prev, steps };
		} );
	};

	const moveStep = ( index, direction ) => {
		const target = index + direction;
		if ( target < 0 || target >= form.steps.length ) {
			return;
		}
		setForm( ( prev ) => {
			const steps = [ ...prev.steps ];
			const [ item ] = steps.splice( index, 1 );
			steps.splice( target, 0, item );
			return { ...prev, steps };
		} );
	};

	const removeStep = ( index ) => {
		setForm( ( prev ) => ( {
			...prev,
			steps: prev.steps.filter( ( _, i ) => i !== index ),
		} ) );
	};

	const addStep = ( type ) => {
		setForm( ( prev ) => ( {
			...prev,
			steps: [ ...prev.steps, normalizeStepForEdit( createEmptyStep( type ) ) ],
		} ) );
	};

	const handleSubmit = async ( e ) => {
		e.preventDefault();
		setFormError( null );

		if ( ! form.title.trim() ) {
			setFormError( __( 'Workflow title is required.', 'agenpress' ) );
			return;
		}

		if ( form.steps.length === 0 ) {
			setFormError( __( 'Add at least one step before saving.', 'agenpress' ) );
			return;
		}

		try {
			const steps = form.steps.map( prepareStepForSave );
			await onSave( {
				title: form.title.trim(),
				description: form.description.trim(),
				enabled: form.enabled,
				trigger_type: form.trigger_type,
				steps,
			} );
		} catch ( err ) {
			setFormError( err.message );
		}
	};

	return (
		<div className="ap-card" style={ { marginBottom: '16px' } }>
			<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' } }>
				<h3 style={ { margin: 0, fontSize: '16px' } }>
					{ workflow.id ? __( 'Edit Workflow', 'agenpress' ) : __( 'New Workflow', 'agenpress' ) }
				</h3>
				<button type="button" className="ap-btn ap-btn-secondary" onClick={ onCancel }>
					{ __( 'Back to list', 'agenpress' ) }
				</button>
			</div>

			{ formError && <div className="ap-alert ap-alert-error" style={ { marginBottom: '12px' } }>{ formError }</div> }

			<form onSubmit={ handleSubmit }>
				<div className="ap-form-group">
					<label className="ap-form-label">{ __( 'Title', 'agenpress' ) }</label>
					<input
						className="ap-form-input"
						value={ form.title }
						onChange={ ( e ) => setForm( { ...form, title: e.target.value } ) }
						required
					/>
				</div>

				<div className="ap-form-group">
					<label className="ap-form-label">{ __( 'Description', 'agenpress' ) }</label>
					<textarea
						className="ap-form-textarea"
						rows={ 2 }
						value={ form.description }
						onChange={ ( e ) => setForm( { ...form, description: e.target.value } ) }
						placeholder={ __( 'Optional notes about what this workflow does', 'agenpress' ) }
					/>
				</div>

				<div style={ { display: 'flex', gap: '24px', flexWrap: 'wrap', marginBottom: '16px' } }>
					<label className="ap-form-checkbox">
						<input
							type="checkbox"
							checked={ form.enabled }
							onChange={ ( e ) => setForm( { ...form, enabled: e.target.checked } ) }
						/>
						{ __( 'Enabled', 'agenpress' ) }
					</label>

					<div className="ap-form-group" style={ { margin: 0, minWidth: '200px' } }>
						<label className="ap-form-label">{ __( 'Trigger', 'agenpress' ) }</label>
						<select
							className="ap-form-select"
							value={ form.trigger_type }
							onChange={ ( e ) => setForm( { ...form, trigger_type: e.target.value } ) }
						>
							<option value="manual">{ __( 'Manual run', 'agenpress' ) }</option>
						</select>
					</div>
				</div>

				<div className="ap-workflow-steps">
					<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' } }>
						<h4 style={ { margin: 0, fontSize: '14px' } }>{ __( 'Steps', 'agenpress' ) }</h4>
						<div style={ { display: 'flex', gap: '6px', flexWrap: 'wrap' } }>
							{ STEP_TYPES.map( ( st ) => (
								<button
									key={ st.id }
									type="button"
									className="ap-btn ap-btn-secondary"
									style={ { padding: '4px 8px', fontSize: '12px' } }
									onClick={ () => addStep( st.id ) }
								>
									+ { st.label }
								</button>
							) ) }
						</div>
					</div>

					{ form.steps.length === 0 ? (
						<p className="ap-empty-state" style={ { padding: '16px 0' } }>
							{ __( 'No steps yet. Add a step type above.', 'agenpress' ) }
						</p>
					) : (
						form.steps.map( ( step, index ) => (
							<div key={ index } className="ap-workflow-step-card">
								<div className="ap-workflow-step-header">
									<strong>
										{ __( 'Step', 'agenpress' ) } { index + 1 }: { STEP_TYPES.find( ( t ) => t.id === step.type )?.label || step.type }
									</strong>
									<div style={ { display: 'flex', gap: '4px' } }>
										<button
											type="button"
											className="ap-btn ap-btn-secondary"
											style={ { padding: '2px 6px', fontSize: '12px' } }
											onClick={ () => moveStep( index, -1 ) }
											disabled={ index === 0 }
											title={ __( 'Move up', 'agenpress' ) }
										>
											↑
										</button>
										<button
											type="button"
											className="ap-btn ap-btn-secondary"
											style={ { padding: '2px 6px', fontSize: '12px' } }
											onClick={ () => moveStep( index, 1 ) }
											disabled={ index === form.steps.length - 1 }
											title={ __( 'Move down', 'agenpress' ) }
										>
											↓
										</button>
										<button
											type="button"
											className="ap-btn ap-btn-danger"
											style={ { padding: '2px 8px', fontSize: '12px' } }
											onClick={ () => removeStep( index ) }
										>
											{ __( 'Remove', 'agenpress' ) }
										</button>
									</div>
								</div>
								<StepFields step={ step } index={ index } onChange={ updateStep } />
							</div>
						) )
					) }
				</div>

				<div style={ { marginTop: '16px', display: 'flex', gap: '8px' } }>
					<button className="ap-btn ap-btn-primary" type="submit" disabled={ saving }>
						{ saving ? __( 'Saving...', 'agenpress' ) : __( 'Save Workflow', 'agenpress' ) }
					</button>
					<button type="button" className="ap-btn ap-btn-secondary" onClick={ onCancel }>
						{ __( 'Cancel', 'agenpress' ) }
					</button>
				</div>
			</form>
		</div>
	);
}

export default function Workflows() {
	const [ workflows, setWorkflows ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ editing, setEditing ] = useState( null );
	const [ saving, setSaving ] = useState( false );

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

	const handleCreate = () => {
		setEditing( {
			id: null,
			title: '',
			description: '',
			enabled: true,
			trigger_type: 'manual',
			steps: [ createEmptyStep( 'ai' ) ],
		} );
		setError( null );
	};

	const handleUsePreset = ( preset ) => {
		const wf = preset.workflow;
		setEditing( {
			id: null,
			title: wf.title,
			description: wf.description,
			enabled: wf.enabled !== false,
			trigger_type: wf.trigger_type || 'manual',
			steps: ( wf.steps || [] ).map( normalizeStepForEdit ),
		} );
		setError( null );
	};

	const workflowPresets = getWorkflowPresets().filter(
		( preset ) => ! preset.requiresWoo || hasWooCommerce
	);

	const handleEdit = ( wf ) => {
		setEditing( wf );
		setError( null );
	};

	const handleSave = async ( data ) => {
		setSaving( true );
		setError( null );

		try {
			if ( editing?.id ) {
				await updateWorkflow( editing.id, data );
			} else {
				await createWorkflow( data );
			}
			setEditing( null );
			await load();
		} catch ( err ) {
			setError( err.message );
			throw err;
		} finally {
			setSaving( false );
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
		if ( ! window.confirm( __( 'Delete this workflow?', 'agenpress' ) ) ) {
			return;
		}

		try {
			await deleteWorkflow( id );
			if ( editing?.id === id ) {
				setEditing( null );
			}
			await load();
		} catch ( err ) {
			setError( err.message );
		}
	};

	if ( editing ) {
		return (
			<div>
				{ error && <div className="ap-alert ap-alert-error">{ error }</div> }
				<WorkflowEditor
					workflow={ editing }
					onSave={ handleSave }
					onCancel={ () => setEditing( null ) }
					saving={ saving }
				/>
			</div>
		);
	}

	return (
		<div>
			{ error && <div className="ap-alert ap-alert-error">{ error }</div> }

			<div className="ap-card" style={ { marginBottom: '16px' } }>
				<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } }>
					<div>
						<h3 style={ { margin: '0 0 4px', fontSize: '16px' } }>
							{ __( 'Workflows', 'agenpress' ) }
						</h3>
						<p style={ { margin: 0, color: '#646970', fontSize: '13px' } }>
							{ __( 'Define reusable multi-step automations and run them on demand.', 'agenpress' ) }
						</p>
					</div>
					<button className="ap-btn ap-btn-primary" type="button" onClick={ handleCreate }>
						{ __( 'New Workflow', 'agenpress' ) }
					</button>
				</div>
			</div>

			<div className="ap-card" style={ { marginBottom: '16px' } }>
				<h3 style={ { margin: '0 0 4px', fontSize: '16px' } }>
					{ __( 'Example Workflows', 'agenpress' ) }
				</h3>
				<p style={ { margin: '0 0 16px', color: '#646970', fontSize: '13px' } }>
					{ __( 'Start from a ready-made template, customize steps, then save and run.', 'agenpress' ) }
				</p>
				<div className="ap-workflow-presets">
					{ workflowPresets.map( ( preset ) => (
						<div key={ preset.id } className="ap-workflow-preset-card">
							<div className="ap-workflow-preset-header">
								<strong>{ preset.title }</strong>
								<span className="ap-badge ap-badge-muted">
									{ preset.workflow.steps.length } { __( 'steps', 'agenpress' ) }
								</span>
							</div>
							<p>{ preset.description }</p>
							{ preset.requiresWoo && (
								<span className="ap-badge ap-badge-running" style={ { marginBottom: '8px' } }>
									WooCommerce
								</span>
							) }
							<button
								type="button"
								className="ap-btn ap-btn-secondary"
								style={ { padding: '4px 10px', fontSize: '12px' } }
								onClick={ () => handleUsePreset( preset ) }
							>
								{ __( 'Use this example', 'agenpress' ) }
							</button>
						</div>
					) ) }
				</div>
			</div>

			<div className="ap-card">
				{ loading ? (
					<p className="ap-empty-state">{ __( 'Loading...', 'agenpress' ) }</p>
				) : workflows.length === 0 ? (
					<p className="ap-empty-state">{ __( 'No workflows yet.', 'agenpress' ) }</p>
				) : (
					<table className="ap-table">
						<thead>
							<tr>
								<th>{ __( 'Title', 'agenpress' ) }</th>
								<th>{ __( 'Status', 'agenpress' ) }</th>
								<th>{ __( 'Trigger', 'agenpress' ) }</th>
								<th>{ __( 'Steps', 'agenpress' ) }</th>
								<th>{ __( 'Last Run', 'agenpress' ) }</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							{ workflows.map( ( wf ) => (
								<tr key={ wf.id }>
									<td>
										<strong>{ wf.title }</strong>
										{ wf.description && (
											<div style={ { fontSize: '12px', color: '#646970', marginTop: '2px' } }>
												{ wf.description }
											</div>
										) }
									</td>
									<td>
										<span className={ `ap-badge ${ wf.enabled ? 'ap-badge-success' : 'ap-badge-muted' }` }>
											{ wf.enabled ? __( 'Enabled', 'agenpress' ) : __( 'Disabled', 'agenpress' ) }
										</span>
									</td>
									<td>{ wf.trigger_type }</td>
									<td>{ ( wf.steps || [] ).length }</td>
									<td>{ wf.last_run_at || '—' }</td>
									<td style={ { whiteSpace: 'nowrap' } }>
										<button
											className="ap-btn ap-btn-secondary"
											style={ { marginRight: '4px', padding: '4px 8px', fontSize: '12px' } }
											onClick={ () => handleEdit( wf ) }
										>
											{ __( 'Edit', 'agenpress' ) }
										</button>
										<button
											className="ap-btn ap-btn-primary"
											style={ { marginRight: '4px', padding: '4px 8px', fontSize: '12px' } }
											onClick={ () => handleRun( wf.id ) }
											disabled={ ! wf.enabled }
										>
											{ __( 'Run', 'agenpress' ) }
										</button>
										<button
											className="ap-btn ap-btn-danger"
											style={ { padding: '4px 8px', fontSize: '12px' } }
											onClick={ () => handleDelete( wf.id ) }
										>
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
