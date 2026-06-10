import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	getTasks,
	createTask,
	toggleTaskPause,
	deleteTask,
	getTask,
	cancelTask,
	retryTask,
	rerunTask,
	getTaskTemplates,
} from '../api';
import TaskProgress from '../components/TaskProgress';

const STATUS_FILTERS = [
	{ id: '', label: __( 'All', 'agenpress' ) },
	{ id: 'running', label: __( 'Running', 'agenpress' ) },
	{ id: 'paused', label: __( 'Paused', 'agenpress' ) },
	{ id: 'completed', label: __( 'Completed', 'agenpress' ) },
	{ id: 'failed', label: __( 'Failed', 'agenpress' ) },
	{ id: 'pending', label: __( 'Pending', 'agenpress' ) },
];

export default function Tasks() {
	const [ tasks, setTasks ] = useState( [] );
	const [ templates, setTemplates ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ statusFilter, setStatusFilter ] = useState( '' );
	const [ selectedTemplate, setSelectedTemplate ] = useState( 'seo_articles' );
	const [ templateParams, setTemplateParams ] = useState( {
		count: 5,
		topic: '',
		sections_count: 4,
		featured_image: false,
		section_images: false,
		include_faq: true,
		include_conclusion: true,
		publish: false,
	} );
	const [ newTitle, setNewTitle ] = useState( '' );
	const [ newDescription, setNewDescription ] = useState( '' );
	const [ creating, setCreating ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ selectedTask, setSelectedTask ] = useState( null );

	const activeTemplate = useMemo(
		() => templates.find( ( t ) => t.id === selectedTemplate ),
		[ templates, selectedTemplate ]
	);

	const hasRunning = useMemo(
		() => tasks.some( ( t ) => t.status === 'running' || t.status === 'pending' ),
		[ tasks ]
	);

	const loadTasks = useCallback( async () => {
		try {
			const data = await getTasks( statusFilter || null );
			setTasks( data );
		} catch ( err ) {
			setError( err.message );
		} finally {
			setLoading( false );
		}
	}, [ statusFilter ] );

	useEffect( () => {
		getTaskTemplates().then( setTemplates ).catch( () => {} );
	}, [] );

	useEffect( () => {
		const template = templates.find( ( t ) => t.id === selectedTemplate );
		if ( ! template?.fields?.length ) {
			return;
		}

		const defaults = {};
		template.fields.forEach( ( field ) => {
			defaults[ field.key ] = field.default ?? ( field.type === 'boolean' ? false : '' );
		} );
		setTemplateParams( defaults );
	}, [ selectedTemplate, templates ] );

	useEffect( () => {
		loadTasks();
		const interval = setInterval( loadTasks, hasRunning ? 2000 : 8000 );
		return () => clearInterval( interval );
	}, [ loadTasks, hasRunning ] );

	const handleCreate = async ( e ) => {
		e.preventDefault();
		if ( ! newTitle.trim() ) return;

		setCreating( true );
		setError( null );

		try {
			await createTask(
				newTitle.trim(),
				newDescription.trim(),
				'admin',
				selectedTemplate,
				templateParams
			);
			setNewTitle( '' );
			setNewDescription( '' );
			await loadTasks();
		} catch ( err ) {
			setError( err.message );
		} finally {
			setCreating( false );
		}
	};

	const handleViewLogs = async ( id ) => {
		try {
			const task = await getTask( id );
			setSelectedTask( task );
		} catch ( err ) {
			setError( err.message );
		}
	};

	const handleAction = async ( action, id ) => {
		try {
			if ( action === 'pause' ) await toggleTaskPause( id );
			if ( action === 'cancel' ) await cancelTask( id );
			if ( action === 'retry' ) await retryTask( id );
			if ( action === 'rerun' ) await rerunTask( id );
			if ( action === 'delete' ) {
				await deleteTask( id );
				if ( selectedTask?.id === id ) setSelectedTask( null );
			}
			await loadTasks();
			if ( selectedTask?.id === id ) {
				const updated = await getTask( id ).catch( () => null );
				if ( updated ) setSelectedTask( updated );
			}
		} catch ( err ) {
			setError( err.message );
		}
	};

	return (
		<div>
			{ error && <div className="ap-alert ap-alert-error">{ error }</div> }

			<div className="ap-card" style={ { marginBottom: '16px' } }>
				<h3 style={ { margin: '0 0 12px', fontSize: '16px' } }>
					{ __( 'Create Agent Task', 'agenpress' ) }
				</h3>
				<form onSubmit={ handleCreate }>
					<div className="ap-form-group">
						<label className="ap-form-label">{ __( 'Task Template', 'agenpress' ) }</label>
						<select
							className="ap-form-select"
							value={ selectedTemplate }
							onChange={ ( e ) => setSelectedTemplate( e.target.value ) }
						>
							{ templates.map( ( t ) => (
								<option key={ t.id } value={ t.id }>{ t.name }</option>
							) ) }
						</select>
						{ activeTemplate?.description && (
							<p style={ { fontSize: '12px', color: '#64748b', marginTop: '4px' } }>
								{ activeTemplate.description }
							</p>
						) }
					</div>

					{ activeTemplate?.fields?.map( ( field ) => (
						<div key={ field.key } className="ap-form-group">
							<label className="ap-form-label">{ field.label }</label>
							{ field.type === 'boolean' ? (
								<label style={ { display: 'flex', alignItems: 'center', gap: '8px', fontSize: '14px' } }>
									<input
										type="checkbox"
										checked={ !! templateParams[ field.key ] }
										onChange={ ( e ) => setTemplateParams( { ...templateParams, [ field.key ]: e.target.checked } ) }
									/>
									{ field.label }
								</label>
							) : (
								<input
									className="ap-form-input"
									type={ field.type === 'number' ? 'number' : 'text' }
									value={ templateParams[ field.key ] ?? field.default ?? '' }
									onChange={ ( e ) => setTemplateParams( {
										...templateParams,
										[ field.key ]: field.type === 'number' ? parseInt( e.target.value, 10 ) : e.target.value,
									} ) }
								/>
							) }
						</div>
					) ) }

					<div className="ap-form-group">
						<label className="ap-form-label">{ __( 'Task Title', 'agenpress' ) }</label>
						<input
							className="ap-form-input"
							value={ newTitle }
							onChange={ ( e ) => setNewTitle( e.target.value ) }
							placeholder={ __( 'e.g. Generate 10 SEO articles about WordPress', 'agenpress' ) }
						/>
					</div>
					<div className="ap-form-group">
						<label className="ap-form-label">{ __( 'Description', 'agenpress' ) }</label>
						<textarea
							className="ap-form-textarea"
							value={ newDescription }
							onChange={ ( e ) => setNewDescription( e.target.value ) }
							placeholder={ __( 'Additional instructions for the agent...', 'agenpress' ) }
						/>
					</div>
					<button className="ap-btn ap-btn-primary" type="submit" disabled={ creating }>
						{ creating ? __( 'Creating...', 'agenpress' ) : __( 'Queue Task', 'agenpress' ) }
					</button>
				</form>
			</div>

			<div className="ap-card">
				<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px', flexWrap: 'wrap', gap: '8px' } }>
					<h3 style={ { margin: 0, fontSize: '16px' } }>{ __( 'Task Queue', 'agenpress' ) }</h3>
					<div style={ { display: 'flex', gap: '4px', flexWrap: 'wrap' } }>
						{ STATUS_FILTERS.map( ( f ) => (
							<button
								key={ f.id }
								className={ `ap-btn ${ statusFilter === f.id ? 'ap-btn-primary' : 'ap-btn-secondary' }` }
								style={ { padding: '4px 10px', fontSize: '12px' } }
								onClick={ () => setStatusFilter( f.id ) }
							>
								{ f.label }
							</button>
						) ) }
					</div>
				</div>

				{ loading ? (
					<p className="ap-empty-state">{ __( 'Loading tasks...', 'agenpress' ) }</p>
				) : tasks.length === 0 ? (
					<p className="ap-empty-state">{ __( 'No tasks yet. Create one above.', 'agenpress' ) }</p>
				) : (
					<table className="ap-table">
						<thead>
							<tr>
								<th>{ __( 'Title', 'agenpress' ) }</th>
								<th>{ __( 'Template', 'agenpress' ) }</th>
								<th>{ __( 'Progress', 'agenpress' ) }</th>
								<th>{ __( 'Actions', 'agenpress' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ tasks.map( ( task ) => (
								<tr key={ task.id }>
									<td>
										<strong>{ task.title }</strong>
										{ task.description && (
											<div style={ { fontSize: '12px', color: '#64748b', marginTop: '2px' } }>
												{ task.description }
											</div>
										) }
									</td>
									<td style={ { fontSize: '12px' } }>{ task.template || 'custom' }</td>
									<td style={ { width: '200px' } }>
										<TaskProgress progress={ task.progress } status={ task.status } />
										<div style={ { fontSize: '11px', color: '#94a3b8', marginTop: '4px' } }>
											{ task.current_step }/{ task.total_steps }
										</div>
									</td>
									<td>
										<div style={ { display: 'flex', gap: '4px', flexWrap: 'wrap' } }>
											{ ( task.status === 'running' || task.status === 'paused' ) && (
												<button className="ap-btn ap-btn-secondary" style={ { padding: '4px 8px', fontSize: '12px' } } onClick={ () => handleAction( 'pause', task.id ) }>
													{ task.status === 'paused' ? __( 'Resume', 'agenpress' ) : __( 'Pause', 'agenpress' ) }
												</button>
											) }
											{ ( task.status === 'running' || task.status === 'paused' || task.status === 'pending' ) && (
												<button className="ap-btn ap-btn-danger" style={ { padding: '4px 8px', fontSize: '12px' } } onClick={ () => handleAction( 'cancel', task.id ) }>
													{ __( 'Cancel', 'agenpress' ) }
												</button>
											) }
											{ task.status === 'failed' && (
												<button className="ap-btn ap-btn-primary" style={ { padding: '4px 8px', fontSize: '12px' } } onClick={ () => handleAction( 'retry', task.id ) }>
													{ __( 'Retry', 'agenpress' ) }
												</button>
											) }
											{ task.status === 'completed' && (
												<button className="ap-btn ap-btn-secondary" style={ { padding: '4px 8px', fontSize: '12px' } } onClick={ () => handleAction( 'rerun', task.id ) }>
													{ __( 'Re-run', 'agenpress' ) }
												</button>
											) }
											<button className="ap-btn ap-btn-secondary" style={ { padding: '4px 8px', fontSize: '12px' } } onClick={ () => handleViewLogs( task.id ) }>
												{ __( 'Details', 'agenpress' ) }
											</button>
											<button className="ap-btn ap-btn-danger" style={ { padding: '4px 8px', fontSize: '12px' } } onClick={ () => handleAction( 'delete', task.id ) }>
												{ __( 'Delete', 'agenpress' ) }
											</button>
										</div>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) }
			</div>

			{ selectedTask && (
				<div className="ap-card" style={ { marginTop: '16px' } }>
					<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' } }>
						<h3 style={ { margin: 0, fontSize: '16px' } }>
							{ __( 'Task Details:', 'agenpress' ) } { selectedTask.title }
						</h3>
						<button className="ap-btn ap-btn-secondary" onClick={ () => setSelectedTask( null ) }>
							{ __( 'Close', 'agenpress' ) }
						</button>
					</div>

					{ selectedTask.steps?.length > 0 && (
						<div style={ { marginBottom: '16px' } }>
							<h4 style={ { fontSize: '14px', margin: '0 0 8px' } }>{ __( 'Steps', 'agenpress' ) }</h4>
							{ selectedTask.steps.map( ( step, i ) => (
								<div key={ i } style={ { display: 'flex', gap: '8px', padding: '6px 0', borderBottom: '1px solid #f1f5f9', fontSize: '13px' } }>
									<span className={ `ap-badge ap-badge-${ step.status === 'completed' ? 'completed' : step.status === 'failed' ? 'failed' : 'pending' }` }>
										{ step.status || 'pending' }
									</span>
									<span>{ step.label || step.name }</span>
								</div>
							) ) }
						</div>
					) }

					<h4 style={ { fontSize: '14px', margin: '0 0 8px' } }>{ __( 'Logs', 'agenpress' ) }</h4>
					{ selectedTask.logs?.length > 0 ? (
						<div style={ { fontSize: '13px', fontFamily: 'monospace', maxHeight: '240px', overflowY: 'auto' } }>
							{ selectedTask.logs.map( ( log, i ) => (
								<div key={ i } style={ { padding: '4px 0', borderBottom: '1px solid #f1f5f9' } }>
									<span style={ { color: '#94a3b8' } }>{ log.created_at }</span>
									{ ' ' }
									<span style={ { color: log.level === 'error' ? '#ef4444' : log.level === 'warning' ? '#f59e0b' : '#334155' } }>
										[{ log.level }] { log.message }
									</span>
								</div>
							) ) }
						</div>
					) : (
						<p style={ { color: '#94a3b8' } }>{ __( 'No logs yet.', 'agenpress' ) }</p>
					) }
				</div>
			) }
		</div>
	);
}
