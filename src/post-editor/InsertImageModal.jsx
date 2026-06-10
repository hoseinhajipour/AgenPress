import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { generateImage } from './api';

const data = window.agenpressPostEditorData || {};

export default function InsertImageModal( { isOpen, onClose, onInsert } ) {
	const [ prompt, setPrompt ] = useState( '' );
	const [ size, setSize ] = useState( data.defaultSize || '1:1' );
	const [ generating, setGenerating ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ preview, setPreview ] = useState( null );

	if ( ! isOpen ) {
		return null;
	}

	const sizes = data.sizes || [
		{ value: '1:1', label: '1:1 (Square)' },
		{ value: '16:9', label: '16:9 (Landscape)' },
		{ value: '9:16', label: '9:16 (Portrait)' },
		{ value: '4:3', label: '4:3 (Landscape)' },
	];

	const handleClose = () => {
		if ( generating ) {
			return;
		}
		setError( null );
		setPreview( null );
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
		setPreview( null );

		try {
			const previewData = await generateImage( prompt.trim(), size );
			setPreview( previewData );
		} catch ( err ) {
			setError( err.message || __( 'Image generation failed.', 'agenpress' ) );
		} finally {
			setGenerating( false );
		}
	};

	const handleInsert = () => {
		if ( ! preview?.url ) {
			return;
		}

		const alt = prompt.trim().replace( /"/g, '&quot;' );
		onInsert(
			`<img src="${ preview.url }" alt="${ alt }" class="aligncenter size-full" />`
		);
		setPreview( null );
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
				aria-labelledby="ap-image-modal-title"
			>
				<div className="ap-fi-modal-header">
					<h2 id="ap-image-modal-title">{ __( 'AI Generate Image', 'agenpress' ) }</h2>
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

					<label className="ap-fi-label" htmlFor="ap-image-prompt">
						{ __( 'Prompt', 'agenpress' ) }
					</label>
					<textarea
						id="ap-image-prompt"
						className="ap-fi-textarea"
						value={ prompt }
						onChange={ ( e ) => setPrompt( e.target.value ) }
						placeholder={ __( 'Describe the image you want to generate...', 'agenpress' ) }
						rows={ 4 }
						disabled={ generating }
					/>

					<label className="ap-fi-label" htmlFor="ap-image-size">
						{ __( 'Dimensions', 'agenpress' ) }
					</label>
					<select
						id="ap-image-size"
						className="ap-fi-select"
						value={ size }
						onChange={ ( e ) => setSize( e.target.value ) }
						disabled={ generating }
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
					{ preview?.url && (
						<button
							type="button"
							className="button button-primary"
							onClick={ handleInsert }
							disabled={ generating }
						>
							{ __( 'Insert Image', 'agenpress' ) }
						</button>
					) }
				</div>
			</div>
		</div>
	);
}
