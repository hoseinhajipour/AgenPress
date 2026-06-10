import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const data = window.agenpressPostEditorData || {};

export default function FeaturedImageModal( { postId, isOpen, onClose, onApplied } ) {
	const [ prompt, setPrompt ] = useState( '' );
	const [ size, setSize ] = useState( '1024x1024' );
	const [ generating, setGenerating ] = useState( false );
	const [ applying, setApplying ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ preview, setPreview ] = useState( null );

	if ( ! isOpen ) {
		return null;
	}

	const sizes = data.sizes || [
		{ value: '1024x1024', label: 'Square (1024×1024)' },
		{ value: '1792x1024', label: 'Landscape (1792×1024)' },
		{ value: '1024x1792', label: 'Portrait (1024×1792)' },
	];

	const handleClose = () => {
		if ( generating || applying ) {
			return;
		}
		setError( null );
		setPreview( null );
		onClose();
	};

	const handleGenerate = async () => {
		if ( ! prompt.trim() ) {
			setError( __( 'Please enter a prompt.', 'agenpress' ) );
			return;
		}

		setGenerating( true );
		setError( null );
		setPreview( null );

		try {
			const response = await apiFetch( {
				path: '/generate-image',
				method: 'POST',
				data: {
					prompt: prompt.trim(),
					size,
				},
			} );

			setPreview( response.data );
		} catch ( err ) {
			setError( err.message || __( 'Image generation failed.', 'agenpress' ) );
		} finally {
			setGenerating( false );
		}
	};

	const handleUseImage = async () => {
		if ( ! preview?.attachment_id || ! postId ) {
			return;
		}

		setApplying( true );
		setError( null );

		try {
			const response = await apiFetch( {
				path: `/posts/${ postId }/featured-image`,
				method: 'POST',
				data: {
					attachment_id: preview.attachment_id,
				},
			} );

			onApplied( response.data );
			setPreview( null );
			setPrompt( '' );
			onClose();
		} catch ( err ) {
			setError( err.message || __( 'Failed to set featured image.', 'agenpress' ) );
		} finally {
			setApplying( false );
		}
	};

	return (
		<div className="ap-fi-modal-overlay" onClick={ handleClose }>
			<div
				className="ap-fi-modal"
				onClick={ ( e ) => e.stopPropagation() }
				role="dialog"
				aria-modal="true"
				aria-labelledby="ap-fi-modal-title"
			>
				<div className="ap-fi-modal-header">
					<h2 id="ap-fi-modal-title">{ __( 'AI Generate Image', 'agenpress' ) }</h2>
					<button
						type="button"
						className="ap-fi-modal-close"
						onClick={ handleClose }
						disabled={ generating || applying }
						aria-label={ __( 'Close', 'agenpress' ) }
					>
						×
					</button>
				</div>

				<div className="ap-fi-modal-body">
					{ error && <div className="ap-fi-error">{ error }</div> }

					<label className="ap-fi-label" htmlFor="ap-fi-prompt">
						{ __( 'Prompt', 'agenpress' ) }
					</label>
					<textarea
						id="ap-fi-prompt"
						className="ap-fi-textarea"
						value={ prompt }
						onChange={ ( e ) => setPrompt( e.target.value ) }
						placeholder={ __( 'Describe the image you want to generate...', 'agenpress' ) }
						rows={ 4 }
						disabled={ generating || applying }
					/>

					<label className="ap-fi-label" htmlFor="ap-fi-size">
						{ __( 'Dimensions', 'agenpress' ) }
					</label>
					<select
						id="ap-fi-size"
						className="ap-fi-select"
						value={ size }
						onChange={ ( e ) => setSize( e.target.value ) }
						disabled={ generating || applying }
					>
						{ sizes.map( ( option ) => (
							<option key={ option.value } value={ option.value }>
								{ option.label }
							</option>
						) ) }
					</select>

					{ preview?.url && (
						<div className="ap-fi-preview">
							<img src={ preview.url } alt={ __( 'Generated preview', 'agenpress' ) } />
						</div>
					) }

					{ generating && (
						<div className="ap-fi-loading">
							{ __( 'Generating image...', 'agenpress' ) }
						</div>
					) }
				</div>

				<div className="ap-fi-modal-footer">
					<button
						type="button"
						className="button"
						onClick={ handleClose }
						disabled={ generating || applying }
					>
						{ __( 'Cancel', 'agenpress' ) }
					</button>
					<button
						type="button"
						className="button button-secondary"
						onClick={ handleGenerate }
						disabled={ generating || applying || ! prompt.trim() }
					>
						{ generating ? __( 'Generating...', 'agenpress' ) : __( 'Generate', 'agenpress' ) }
					</button>
					{ ! postId && (
						<p className="ap-fi-hint">
							{ __( 'Save the post as a draft first to set a featured image.', 'agenpress' ) }
						</p>
					) }
					{ preview?.attachment_id && (
						<button
							type="button"
							className="button button-primary"
							onClick={ handleUseImage }
							disabled={ generating || applying || ! postId }
						>
							{ applying ? __( 'Applying...', 'agenpress' ) : __( 'Use Image', 'agenpress' ) }
						</button>
					) }
				</div>
			</div>
		</div>
	);
}
