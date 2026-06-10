import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	getMemory,
	createMemory,
	updateMemory,
	deleteMemory,
	searchMemory,
	extractBrandMemory,
	reindexMemory,
} from '../api';

const CATEGORY_LABELS = {
	brand: __( 'Brand', 'agenpress' ),
	contact: __( 'Contact', 'agenpress' ),
	design: __( 'Design', 'agenpress' ),
	seo: __( 'SEO', 'agenpress' ),
	general: __( 'General', 'agenpress' ),
};

export default function Memory() {
	const [ entries, setEntries ] = useState( [] );
	const [ categories, setCategories ] = useState( [] );
	const [ embeddingsAvailable, setEmbeddingsAvailable ] = useState( false );
	const [ filter, setFilter ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ semanticMode, setSemanticMode ] = useState( false );
	const [ loading, setLoading ] = useState( true );
	const [ form, setForm ] = useState( { category: 'brand', key_name: '', value: '' } );
	const [ editingId, setEditingId ] = useState( null );
	const [ editForm, setEditForm ] = useState( { category: 'brand', key_name: '', value: '' } );
	const [ saving, setSaving ] = useState( false );
	const [ extracting, setExtracting ] = useState( false );
	const [ reindexing, setReindexing ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ success, setSuccess ] = useState( null );

	const loadMemory = useCallback( async () => {
		setLoading( true );
		setError( null );

		try {
			if ( semanticMode && search.trim() ) {
				const data = await searchMemory( search.trim(), filter || null );
				setEntries( data.results || [] );
				setCategories( Object.keys( CATEGORY_LABELS ) );
			} else {
				const data = await getMemory( filter || null, search );
				setEntries( data.entries || [] );
				setCategories( data.categories || [] );
				setEmbeddingsAvailable( !! data.embeddings_available );
			}
		} catch ( err ) {
			setError( err.message );
		} finally {
			setLoading( false );
		}
	}, [ filter, search, semanticMode ] );

	useEffect( () => {
		const timer = setTimeout( () => {
			loadMemory();
		}, semanticMode ? 400 : 0 );

		return () => clearTimeout( timer );
	}, [ loadMemory, semanticMode ] );

	const handleCreate = async ( e ) => {
		e.preventDefault();
		if ( ! form.key_name.trim() || ! form.value.trim() ) return;

		setSaving( true );
		setError( null );
		setSuccess( null );

		try {
			await createMemory( form.category, form.key_name.trim(), form.value.trim() );
			setForm( { ...form, key_name: '', value: '' } );
			setSuccess( __( 'Memory entry saved.', 'agenpress' ) );
			await loadMemory();
		} catch ( err ) {
			setError( err.message );
		} finally {
			setSaving( false );
		}
	};

	const startEdit = ( entry ) => {
		setEditingId( entry.id );
		setEditForm( {
			category: entry.category,
			key_name: entry.key_name,
			value: entry.value,
		} );
	};

	const cancelEdit = () => {
		setEditingId( null );
	};

	const handleUpdate = async ( id ) => {
		if ( ! editForm.key_name.trim() || ! editForm.value.trim() ) return;

		setSaving( true );
		setError( null );
		setSuccess( null );

		try {
			await updateMemory( id, editForm );
			setEditingId( null );
			setSuccess( __( 'Memory entry updated.', 'agenpress' ) );
			await loadMemory();
		} catch ( err ) {
			setError( err.message );
		} finally {
			setSaving( false );
		}
	};

	const handleDelete = async ( id ) => {
		try {
			await deleteMemory( id );
			if ( editingId === id ) {
				setEditingId( null );
			}
			await loadMemory();
		} catch ( err ) {
			setError( err.message );
		}
	};

	const handleExtractBrand = async () => {
		setExtracting( true );
		setError( null );
		setSuccess( null );

		try {
			const result = await extractBrandMemory();
			setSuccess(
				/* translators: 1: created count, 2: updated count */
				__( 'Brand info imported: %1$d created, %2$d updated.', 'agenpress' )
					.replace( '%1$d', String( result.created || 0 ) )
					.replace( '%2$d', String( result.updated || 0 ) )
			);
			await loadMemory();
		} catch ( err ) {
			setError( err.message );
		} finally {
			setExtracting( false );
		}
	};

	const handleReindex = async () => {
		setReindexing( true );
		setError( null );
		setSuccess( null );

		try {
			const result = await reindexMemory();
			setSuccess(
				/* translators: 1: embedded count, 2: processed count */
				__( 'Reindexed embeddings: %1$d of %2$d entries.', 'agenpress' )
					.replace( '%1$d', String( result.embedded || 0 ) )
					.replace( '%2$d', String( result.processed || 0 ) )
			);
			await loadMemory();
		} catch ( err ) {
			setError( err.message );
		} finally {
			setReindexing( false );
		}
	};

	return (
		<div>
			{ error && <div className="ap-alert ap-alert-error">{ error }</div> }
			{ success && <div className="ap-alert ap-alert-success">{ success }</div> }

			<div className="ap-card" style={ { marginBottom: '16px' } }>
				<div style={ { display: 'flex', gap: '8px', marginBottom: '16px', flexWrap: 'wrap', alignItems: 'center' } }>
					<h3 style={ { margin: 0, fontSize: '16px', flex: 1 } }>
						{ __( 'Site Memory', 'agenpress' ) }
					</h3>
					<button
						className="ap-btn ap-btn-secondary"
						onClick={ handleExtractBrand }
						disabled={ extracting }
					>
						{ extracting ? __( 'Extracting...', 'agenpress' ) : __( 'Auto-Extract Brand Info', 'agenpress' ) }
					</button>
					{ embeddingsAvailable && (
						<button
							className="ap-btn ap-btn-secondary"
							onClick={ handleReindex }
							disabled={ reindexing }
						>
							{ reindexing ? __( 'Reindexing...', 'agenpress' ) : __( 'Reindex Embeddings', 'agenpress' ) }
						</button>
					) }
				</div>
				<p style={ { margin: '0 0 16px', color: '#646970', fontSize: '13px' } }>
					{ embeddingsAvailable
						? __( 'Memory entries are embedded for semantic retrieval in AI prompts.', 'agenpress' )
						: __( 'Configure an OpenAI API key in Settings to enable semantic memory search.', 'agenpress' ) }
				</p>
			</div>

			<div className="ap-card" style={ { marginBottom: '16px' } }>
				<h3 style={ { margin: '0 0 12px', fontSize: '16px' } }>
					{ __( 'Add Memory Entry', 'agenpress' ) }
				</h3>
				<form onSubmit={ handleCreate }>
					<div style={ { display: 'grid', gridTemplateColumns: '1fr 2fr', gap: '12px' } }>
						<div className="ap-form-group">
							<label className="ap-form-label">{ __( 'Category', 'agenpress' ) }</label>
							<select
								className="ap-form-select"
								value={ form.category }
								onChange={ ( e ) => setForm( { ...form, category: e.target.value } ) }
							>
								{ Object.entries( CATEGORY_LABELS ).map( ( [ key, label ] ) => (
									<option key={ key } value={ key }>{ label }</option>
								) ) }
							</select>
						</div>
						<div className="ap-form-group">
							<label className="ap-form-label">{ __( 'Key', 'agenpress' ) }</label>
							<input
								className="ap-form-input"
								value={ form.key_name }
								onChange={ ( e ) => setForm( { ...form, key_name: e.target.value } ) }
								placeholder={ __( 'e.g. brand_name, primary_color', 'agenpress' ) }
							/>
						</div>
					</div>
					<div className="ap-form-group">
						<label className="ap-form-label">{ __( 'Value', 'agenpress' ) }</label>
						<textarea
							className="ap-form-textarea"
							value={ form.value }
							onChange={ ( e ) => setForm( { ...form, value: e.target.value } ) }
							placeholder={ __( 'Store brand info, design rules, SEO keywords...', 'agenpress' ) }
						/>
					</div>
					<button className="ap-btn ap-btn-primary" type="submit" disabled={ saving }>
						{ saving ? __( 'Saving...', 'agenpress' ) : __( 'Save Memory', 'agenpress' ) }
					</button>
				</form>
			</div>

			<div className="ap-card">
				<div style={ { display: 'flex', gap: '8px', marginBottom: '16px', flexWrap: 'wrap', alignItems: 'center' } }>
					<button
						className={ `ap-btn ${ ! filter ? 'ap-btn-primary' : 'ap-btn-secondary' }` }
						onClick={ () => setFilter( '' ) }
					>
						{ __( 'All', 'agenpress' ) }
					</button>
					{ categories.map( ( cat ) => (
						<button
							key={ cat }
							className={ `ap-btn ${ filter === cat ? 'ap-btn-primary' : 'ap-btn-secondary' }` }
							onClick={ () => setFilter( cat ) }
						>
							{ CATEGORY_LABELS[ cat ] || cat }
						</button>
					) ) }
					<input
						className="ap-form-input"
						style={ { maxWidth: '220px', marginLeft: 'auto' } }
						placeholder={ semanticMode ? __( 'Semantic search...', 'agenpress' ) : __( 'Search...', 'agenpress' ) }
						value={ search }
						onChange={ ( e ) => setSearch( e.target.value ) }
					/>
					{ embeddingsAvailable && (
						<label style={ { display: 'flex', alignItems: 'center', gap: '6px', fontSize: '13px' } }>
							<input
								type="checkbox"
								checked={ semanticMode }
								onChange={ ( e ) => setSemanticMode( e.target.checked ) }
							/>
							{ __( 'Semantic', 'agenpress' ) }
						</label>
					) }
				</div>

				{ loading ? (
					<p className="ap-empty-state">{ __( 'Loading...', 'agenpress' ) }</p>
				) : entries.length === 0 ? (
					<p className="ap-empty-state">{ __( 'No memory entries yet.', 'agenpress' ) }</p>
				) : (
					<table className="ap-table">
						<thead>
							<tr>
								<th>{ __( 'Category', 'agenpress' ) }</th>
								<th>{ __( 'Key', 'agenpress' ) }</th>
								<th>{ __( 'Value', 'agenpress' ) }</th>
								{ semanticMode && <th>{ __( 'Score', 'agenpress' ) }</th> }
								<th></th>
							</tr>
						</thead>
						<tbody>
							{ entries.map( ( entry ) => (
								<tr key={ entry.id }>
									{ editingId === entry.id ? (
										<>
											<td colSpan={ semanticMode ? 5 : 4 }>
												<div style={ { display: 'grid', gap: '8px' } }>
													<div style={ { display: 'grid', gridTemplateColumns: '1fr 2fr', gap: '8px' } }>
														<select
															className="ap-form-select"
															value={ editForm.category }
															onChange={ ( e ) => setEditForm( { ...editForm, category: e.target.value } ) }
														>
															{ Object.entries( CATEGORY_LABELS ).map( ( [ key, label ] ) => (
																<option key={ key } value={ key }>{ label }</option>
															) ) }
														</select>
														<input
															className="ap-form-input"
															value={ editForm.key_name }
															onChange={ ( e ) => setEditForm( { ...editForm, key_name: e.target.value } ) }
														/>
													</div>
													<textarea
														className="ap-form-textarea"
														value={ editForm.value }
														onChange={ ( e ) => setEditForm( { ...editForm, value: e.target.value } ) }
													/>
													<div style={ { display: 'flex', gap: '8px' } }>
														<button
															className="ap-btn ap-btn-primary"
															onClick={ () => handleUpdate( entry.id ) }
															disabled={ saving }
														>
															{ __( 'Save', 'agenpress' ) }
														</button>
														<button className="ap-btn ap-btn-secondary" onClick={ cancelEdit }>
															{ __( 'Cancel', 'agenpress' ) }
														</button>
													</div>
												</div>
											</td>
										</>
									) : (
										<>
											<td>
												<span className="ap-badge ap-badge-pending">
													{ CATEGORY_LABELS[ entry.category ] || entry.category }
												</span>
												{ entry.has_embedding && (
													<span className="ap-badge" style={ { marginLeft: '4px', fontSize: '10px' } }>
														{ __( 'RAG', 'agenpress' ) }
													</span>
												) }
											</td>
											<td><strong>{ entry.key_name }</strong></td>
											<td style={ { maxWidth: '400px' } }>{ entry.value }</td>
											{ semanticMode && (
												<td>{ entry.score != null ? entry.score.toFixed( 3 ) : '—' }</td>
											) }
											<td>
												<div style={ { display: 'flex', gap: '4px' } }>
													<button
														className="ap-btn ap-btn-secondary"
														style={ { padding: '4px 8px', fontSize: '12px' } }
														onClick={ () => startEdit( entry ) }
													>
														{ __( 'Edit', 'agenpress' ) }
													</button>
													<button
														className="ap-btn ap-btn-danger"
														style={ { padding: '4px 8px', fontSize: '12px' } }
														onClick={ () => handleDelete( entry.id ) }
													>
														{ __( 'Delete', 'agenpress' ) }
													</button>
												</div>
											</td>
										</>
									) }
								</tr>
							) ) }
						</tbody>
					</table>
				) }
			</div>
		</div>
	);
}
