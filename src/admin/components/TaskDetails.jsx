import { useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import TaskProgress from './TaskProgress';

const STATUS_ICON = {
	completed: '✓',
	failed: '✕',
	running: '…',
	pending: '○',
};

function parseStepResult( result ) {
	if ( Array.isArray( result ) ) {
		return result;
	}

	if ( typeof result === 'string' ) {
		try {
			const parsed = JSON.parse( result );
			return parsed;
		} catch {
			return result;
		}
	}

	return result;
}

function BoolBadge( { value, label } ) {
	return (
		<span
			className={ `ap-badge ${ value ? 'ap-badge-completed' : 'ap-badge-pending' }` }
			style={ { fontSize: '11px', marginRight: '6px', marginBottom: '4px', display: 'inline-block' } }
		>
			{ label }: { value ? __( 'Yes', 'agenpress' ) : __( 'No', 'agenpress' ) }
		</span>
	);
}

function SeoArticleReport( { report, post } ) {
	if ( ! report ) {
		return null;
	}

	const editUrl = report.post_id
		? `${ window.agenpressData?.adminUrl || '/wp-admin/' }post.php?post=${ report.post_id }&action=edit`
		: null;

	return (
		<div style={ { marginTop: '8px', fontSize: '12px', color: '#475569' } }>
			{ report.published_title && report.published_title !== report.planned_title && (
				<div style={ { marginBottom: '6px' } }>
					<strong>{ __( 'Published title:', 'agenpress' ) }</strong> { report.published_title }
				</div>
			) }
			{ report.topic && (
				<div style={ { marginBottom: '6px' } }>
					<strong>{ __( 'Topic:', 'agenpress' ) }</strong> { report.topic }
				</div>
			) }

			<div style={ { display: 'flex', flexWrap: 'wrap', gap: '4px', marginBottom: '8px' } }>
				<span className="ap-badge ap-badge-pending" style={ { fontSize: '11px' } }>
					{ sprintf(
						/* translators: 1: sections created, 2: sections expected */
						__( 'Sections: %1$d / %2$d', 'agenpress' ),
						report.sections_count ?? 0,
						report.sections_expected ?? 0
					) }
				</span>
				{ report.section_images_requested > 0 && (
					<span className="ap-badge ap-badge-pending" style={ { fontSize: '11px' } }>
						{ sprintf(
							/* translators: 1: generated images, 2: requested images */
							__( 'Section images: %1$d / %2$d', 'agenpress' ),
							report.section_images_generated ?? 0,
							report.section_images_requested ?? 0
						) }
					</span>
				) }
				{ report.faq_requested && (
					<span className="ap-badge ap-badge-pending" style={ { fontSize: '11px' } }>
						{ sprintf(
							/* translators: %d: FAQ question count */
							__( 'FAQ: %d questions', 'agenpress' ),
							report.faq_count ?? 0
						) }
					</span>
				) }
				<span className={ `ap-badge ap-badge-${ post?.status === 'publish' ? 'completed' : 'pending' }` } style={ { fontSize: '11px' } }>
					{ post?.status || report.post_status || 'draft' }
				</span>
			</div>

			<div style={ { marginBottom: '8px' } }>
				<BoolBadge value={ report.featured_image_set } label={ __( 'Featured image', 'agenpress' ) } />
				<BoolBadge value={ report.conclusion_included } label={ __( 'Conclusion', 'agenpress' ) } />
				<BoolBadge value={ report.faq_schema_saved } label={ __( 'FAQ schema', 'agenpress' ) } />
			</div>

			{ report.image_logs?.length > 0 && (
				<div style={ { marginBottom: '8px' } }>
					<strong>{ __( 'Image generation:', 'agenpress' ) }</strong>
					<ul style={ { margin: '4px 0 0', paddingLeft: '18px' } }>
						{ report.image_logs.map( ( entry, i ) => (
							<li
								key={ i }
								style={ { color: entry.success ? '#15803d' : '#b91c1c', marginBottom: '4px' } }
							>
								{ entry.message || entry.error || entry.label }
								{ entry.error_code && (
									<span style={ { color: '#94a3b8', marginLeft: '6px' } }>
										({ entry.error_code })
									</span>
								) }
							</li>
						) ) }
					</ul>
				</div>
			) }

			{ report.section_headings?.length > 0 && (
				<div style={ { marginBottom: '8px' } }>
					<strong>{ __( 'Section headings:', 'agenpress' ) }</strong>
					<ol style={ { margin: '4px 0 0', paddingLeft: '18px' } }>
						{ report.section_headings.map( ( heading, i ) => (
							<li key={ i }>{ heading }</li>
						) ) }
					</ol>
				</div>
			) }

			{ ( report.categories?.length > 0 || report.tags?.length > 0 ) && (
				<div style={ { marginBottom: '8px' } }>
					{ report.categories?.length > 0 && (
						<div><strong>{ __( 'Categories:', 'agenpress' ) }</strong> { report.categories.join( ', ' ) }</div>
					) }
					{ report.tags?.length > 0 && (
						<div><strong>{ __( 'Tags:', 'agenpress' ) }</strong> { report.tags.join( ', ' ) }</div>
					) }
				</div>
			) }

			{ ( report.meta_title || report.meta_description ) && (
				<div style={ { marginBottom: '8px', padding: '8px', background: '#f8fafc', borderRadius: '6px' } }>
					{ report.meta_title && (
						<div><strong>{ __( 'Meta title:', 'agenpress' ) }</strong> { report.meta_title }</div>
					) }
					{ report.meta_description && (
						<div style={ { marginTop: '4px' } }><strong>{ __( 'Meta description:', 'agenpress' ) }</strong> { report.meta_description }</div>
					) }
				</div>
			) }

			{ ( post?.url || editUrl ) && (
				<div style={ { display: 'flex', gap: '12px', flexWrap: 'wrap' } }>
					{ post?.url && (
						<a href={ post.url } target="_blank" rel="noreferrer">
							{ __( 'View post', 'agenpress' ) }
						</a>
					) }
					{ editUrl && (
						<a href={ editUrl } target="_blank" rel="noreferrer">
							{ __( 'Edit in WordPress', 'agenpress' ) }
						</a>
					) }
				</div>
			) }
		</div>
	);
}

function PlannedTitles( { titles } ) {
	if ( ! Array.isArray( titles ) || titles.length === 0 ) {
		return null;
	}

	return (
		<div style={ { marginTop: '8px', fontSize: '12px', color: '#475569' } }>
			<strong>{ __( 'Planned article titles:', 'agenpress' ) }</strong>
			<ol style={ { margin: '4px 0 0', paddingLeft: '18px' } }>
				{ titles.map( ( title, i ) => (
					<li key={ i }>{ title }</li>
				) ) }
			</ol>
		</div>
	);
}

function StepErrorDetail( { step } ) {
	const errorData = step.last_error_data || {};
	const message = step.last_error || errorData.parse_error;

	if ( ! message && ! errorData.response_preview ) {
		return null;
	}

	return (
		<div style={ { marginTop: '8px', padding: '8px 10px', background: '#fef2f2', borderRadius: '6px', fontSize: '12px', color: '#b91c1c' } }>
			{ message && <div><strong>{ __( 'Error:', 'agenpress' ) }</strong> { message }</div> }
			{ errorData.parse_error && errorData.parse_error !== message && (
				<div style={ { marginTop: '4px' } }>{ errorData.parse_error }</div>
			) }
			{ errorData.response_preview && (
				<div style={ { marginTop: '6px', color: '#64748b', fontFamily: 'monospace', fontSize: '11px', whiteSpace: 'pre-wrap', wordBreak: 'break-word' } }>
					<strong>{ __( 'AI response preview:', 'agenpress' ) }</strong>
					{ '\n' }
					{ errorData.response_preview }
				</div>
			) }
		</div>
	);
}

function StepDetail( { step } ) {
	if ( step.status === 'failed' ) {
		return <StepErrorDetail step={ step } />;
	}

	const result = parseStepResult( step.result );

	if ( step.type === 'seo_article' && result && typeof result === 'object' && ! Array.isArray( result ) ) {
		return <SeoArticleReport report={ result.report } post={ result } />;
	}

	if ( step.name === 'plan' && Array.isArray( result ) ) {
		return <PlannedTitles titles={ result } />;
	}

	if ( step.name === 'summary' && typeof result === 'string' && result.trim() ) {
		return (
			<div style={ { marginTop: '8px', fontSize: '12px', color: '#475569' } }>
				{ result }
			</div>
		);
	}

	if ( typeof result === 'string' && result.trim() && result.length < 500 ) {
		return (
			<div style={ { marginTop: '8px', fontSize: '12px', color: '#64748b' } }>
				{ result }
			</div>
		);
	}

	return null;
}

function ArticleSummaryTable( { steps } ) {
	const articles = useMemo(
		() => ( steps || [] )
			.filter( ( step ) => step.type === 'seo_article' )
			.map( ( step, index ) => {
				const result = parseStepResult( step.result );
				const report = result?.report;
				return {
					key: step.name || index,
					label: step.label,
					status: step.status,
					report,
					post: result,
				};
			} ),
		[ steps ]
	);

	if ( articles.length === 0 ) {
		return null;
	}

	return (
		<div style={ { marginBottom: '16px' } }>
			<h4 style={ { fontSize: '14px', margin: '0 0 8px' } }>{ __( 'Articles overview', 'agenpress' ) }</h4>
			<table className="ap-table" style={ { fontSize: '12px' } }>
				<thead>
					<tr>
						<th>{ __( 'Article', 'agenpress' ) }</th>
						<th>{ __( 'Status', 'agenpress' ) }</th>
						<th>{ __( 'Sections', 'agenpress' ) }</th>
						<th>{ __( 'Images', 'agenpress' ) }</th>
						<th>{ __( 'FAQ', 'agenpress' ) }</th>
						<th>{ __( 'Post', 'agenpress' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ articles.map( ( article ) => (
						<tr key={ article.key }>
							<td>
								<strong>{ article.report?.published_title || article.label }</strong>
							</td>
							<td>
								<span className={ `ap-badge ap-badge-${ article.status === 'completed' ? 'completed' : article.status === 'failed' ? 'failed' : 'pending' }` }>
									{ article.status || 'pending' }
								</span>
							</td>
							<td>
								{ article.report
									? `${ article.report.sections_count ?? 0 } / ${ article.report.sections_expected ?? '—' }`
									: '—' }
							</td>
							<td>
								{ article.report?.section_images_requested > 0
									? `${ article.report.section_images_generated ?? 0 } / ${ article.report.section_images_requested }`
									: article.report?.featured_image_set
										? __( 'Featured only', 'agenpress' )
										: '—' }
								{ article.report?.image_error_count > 0 && (
									<div style={ { color: '#b91c1c', fontSize: '11px' } }>
										{ sprintf(
											/* translators: %d: image error count */
											__( '%d image error(s)', 'agenpress' ),
											article.report.image_error_count
										) }
									</div>
								) }
							</td>
							<td>{ article.report?.faq_count ?? '—' }</td>
							<td>
								{ article.post?.url ? (
									<a href={ article.post.url } target="_blank" rel="noreferrer">
										{ __( 'Open', 'agenpress' ) }
									</a>
								) : (
									'—'
								) }
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}

export default function TaskDetails( { task, onClose } ) {
	const articleSteps = useMemo(
		() => ( task.steps || [] ).filter( ( step ) => step.type === 'seo_article' ),
		[ task.steps ]
	);

	return (
		<div className="ap-card" style={ { marginTop: '16px' } }>
			<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '12px', gap: '12px' } }>
				<div style={ { flex: 1 } }>
					<h3 style={ { margin: '0 0 8px', fontSize: '16px' } }>
						{ __( 'Task Details:', 'agenpress' ) } { task.title }
					</h3>
					<div style={ { maxWidth: '320px' } }>
						<TaskProgress progress={ task.progress } status={ task.status } />
						<div style={ { fontSize: '11px', color: '#94a3b8', marginTop: '4px' } }>
							{ sprintf(
								/* translators: 1: current step, 2: total steps, 3: template id */
								__( 'Step %1$d of %2$d · Template: %3$s', 'agenpress' ),
								task.current_step ?? 0,
								task.total_steps ?? 0,
								task.template || 'custom'
							) }
						</div>
					</div>
				</div>
				<button className="ap-btn ap-btn-secondary" onClick={ onClose }>
					{ __( 'Close', 'agenpress' ) }
				</button>
			</div>

			{ task.description && (
				<p style={ { fontSize: '13px', color: '#64748b', margin: '0 0 12px' } }>{ task.description }</p>
			) }

			{ task.error_message && (
				<div className="ap-alert ap-alert-error" style={ { marginBottom: '12px' } }>
					{ task.error_message }
				</div>
			) }

			{ task.final_result?.summary && (
				<div style={ { marginBottom: '16px', padding: '10px 12px', background: '#f0fdf4', borderRadius: '6px', fontSize: '13px' } }>
					<strong>{ __( 'Summary:', 'agenpress' ) }</strong> { task.final_result.summary }
				</div>
			) }

			{ articleSteps.length > 0 && <ArticleSummaryTable steps={ task.steps } /> }

			{ task.steps?.length > 0 && (
				<div style={ { marginBottom: '16px' } }>
					<h4 style={ { fontSize: '14px', margin: '0 0 8px' } }>{ __( 'Steps', 'agenpress' ) }</h4>
					{ task.steps.map( ( step, i ) => (
						<div
							key={ i }
							style={ {
								padding: '10px 12px',
								marginBottom: '8px',
								border: '1px solid #e2e8f0',
								borderRadius: '8px',
								background: step.status === 'failed' ? '#fef2f2' : '#fff',
							} }
						>
							<div style={ { display: 'flex', alignItems: 'center', gap: '8px', fontSize: '13px' } }>
								<span style={ { width: '18px', textAlign: 'center', color: '#64748b' } }>
									{ STATUS_ICON[ step.status ] || STATUS_ICON.pending }
								</span>
								<span className={ `ap-badge ap-badge-${ step.status === 'completed' ? 'completed' : step.status === 'failed' ? 'failed' : 'pending' }` }>
									{ step.status || 'pending' }
								</span>
								<strong>{ step.label || step.name }</strong>
								{ step.type && (
									<span style={ { fontSize: '11px', color: '#94a3b8' } }>({ step.type })</span>
								) }
							</div>
							<StepDetail step={ step } />
						</div>
					) ) }
				</div>
			) }

			<h4 style={ { fontSize: '14px', margin: '0 0 8px' } }>{ __( 'Logs', 'agenpress' ) }</h4>
			{ task.logs?.length > 0 ? (
				<div style={ { fontSize: '12px', maxHeight: '280px', overflowY: 'auto', border: '1px solid #e2e8f0', borderRadius: '8px' } }>
					{ task.logs.map( ( log, i ) => (
						<div
							key={ i }
							style={ {
								padding: '8px 12px',
								borderBottom: i < task.logs.length - 1 ? '1px solid #f1f5f9' : 'none',
							} }
						>
							<div style={ { color: '#94a3b8', fontSize: '11px', marginBottom: '2px' } }>
								{ log.created_at }
								{ log.step_index > 0 && (
									<span> · { sprintf( __( 'Step %d', 'agenpress' ), log.step_index ) }</span>
								) }
							</div>
							<div style={ { color: log.level === 'error' ? '#ef4444' : log.level === 'warning' ? '#f59e0b' : '#334155' } }>
								[{ log.level }] { log.message }
							</div>
							{ log.context?.message && log.context.message !== log.message && (
								<div style={ { color: '#64748b', marginTop: '4px' } }>{ log.context.message }</div>
							) }
							{ log.context?.parse_error && (
								<div style={ { color: '#b91c1c', marginTop: '4px' } }>{ log.context.parse_error }</div>
							) }
							{ log.context?.response_preview && (
								<div style={ { marginTop: '4px', color: '#64748b', fontFamily: 'monospace', fontSize: '11px', whiteSpace: 'pre-wrap', wordBreak: 'break-word' } }>
									{ log.context.response_preview }
								</div>
							) }
							{ log.context?.image_log && (
								<div
									style={ {
										marginTop: '4px',
										padding: '6px 8px',
										background: log.context.image_log.success ? '#f0fdf4' : '#fef2f2',
										borderRadius: '4px',
										color: log.context.image_log.success ? '#15803d' : '#b91c1c',
									} }
								>
									{ log.context.image_log.message || log.context.image_log.error }
									{ log.context.image_log.error_code && (
										<span style={ { color: '#94a3b8', marginLeft: '6px' } }>
											({ log.context.image_log.error_code })
										</span>
									) }
								</div>
							) }
							{ log.context?.report && ! log.context?.image_log && (
								<div style={ { marginTop: '6px', padding: '6px 8px', background: '#f8fafc', borderRadius: '4px' } }>
									<SeoArticleReport report={ log.context.report } post={ { status: log.context.report.post_status } } />
								</div>
							) }
						</div>
					) ) }
				</div>
			) : (
				<p style={ { color: '#94a3b8' } }>{ __( 'No logs yet.', 'agenpress' ) }</p>
			) }
		</div>
	);
}
