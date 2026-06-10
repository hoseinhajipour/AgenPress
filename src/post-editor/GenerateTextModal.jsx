import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function GenerateTextModal( { isOpen, selection, onClose, onInsert } ) {
	const [ prompt, setPrompt ] = useState( '' );
	const [ generating, setGenerating ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ preview, setPreview ] = useState( '' );

	if ( ! isOpen ) {
		return null;
	}

	const handleClose = () => {
		if ( generating ) {
			return;
		}
		setError( null );
		setPreview( '' );
		setPrompt( '' );
		onClose();
	};

	const handleGenerate = async () => {
		if ( ! prompt.trim() ) {
			setError( __( 'Please enter a prompt.', 'agenpress' ) );
			return;
		}

		setGenerating( true );
		setError( null );
		setPreview( '' );

		try {
			const response = await apiFetch( {
				path: '/generate-text',
				method: 'POST',
				data: {
					prompt: prompt.trim(),
					context: selection || '',
				},
			} );

			setPreview( response.data?.content || '' );
		} catch ( err ) {
			setError( err.message || __( 'Text generation failed.', 'agenpress' ) );
		} finally {
			setGenerating( false );
		}
	};

	const handleInsert = () => {
		if ( ! preview ) {
			return;
		}
		onInsert( preview );
		setPreview( '' );
		setPrompt( '' );
		onClose();
	};

	return (
		<div className="ap-fi-modal-overlay" onClick={ handleClose }>
			<div
				className="ap-fi-modal"
				onClick={ ( e ) => e.stopPropagation() }
				role="dialog"
				aria-modal="true"
				aria-labelledby="ap-text-modal-title"
			>
				<div className="ap-fi-modal-header">
					<h2 id="ap-text-modal-title">{ __( 'AI Generate Text', 'agenpress' ) }</h2>
					<button
						type="button"
						className="ap-fi-modal-close"
						onClick={ handleClose }
						disabled={ generating }
						aria-label={ __( 'Close', 'agenpress' ) }
					>
						×
					</button>
				</div>

				<div className="ap-fi-modal-body">
					{ error && <div className="ap-fi-error">{ error }</div> }

					{ selection && (
						<>
							<label className="ap-fi-label">{ __( 'Selected text', 'agenpress' ) }</label>
							<div className="ap-fi-context">{ selection }</div>
						</>
					) }

					<label className="ap-fi-label" htmlFor="ap-text-prompt">
						{ __( 'Prompt', 'agenpress' ) }
					</label>
					<textarea
						id="ap-text-prompt"
						className="ap-fi-textarea"
						value={ prompt }
						onChange={ ( e ) => setPrompt( e.target.value ) }
						placeholder={ __( 'Describe the text you want to generate...', 'agenpress' ) }
						rows={ 4 }
						disabled={ generating }
					/>

					{ preview && (
						<>
							<label className="ap-fi-label">{ __( 'Preview', 'agenpress' ) }</label>
							<div
								className="ap-fi-preview ap-fi-preview-text"
								dangerouslySetInnerHTML={ { __html: preview } }
							/>
						</>
					) }

					{ generating && (
						<div className="ap-fi-loading">
							{ __( 'Generating text...', 'agenpress' ) }
						</div>
					) }
				</div>

				<div className="ap-fi-modal-footer">
					<button
						type="button"
						className="button"
						onClick={ handleClose }
						disabled={ generating }
					>
						{ __( 'Cancel', 'agenpress' ) }
					</button>
					<button
						type="button"
						className="button button-secondary"
						onClick={ handleGenerate }
						disabled={ generating || ! prompt.trim() }
					>
						{ generating ? __( 'Generating...', 'agenpress' ) : __( 'Generate', 'agenpress' ) }
					</button>
					{ preview && (
						<button
							type="button"
							className="button button-primary"
							onClick={ handleInsert }
							disabled={ generating }
						>
							{ __( 'Insert Text', 'agenpress' ) }
						</button>
					) }
				</div>
			</div>
		</div>
	);
}
