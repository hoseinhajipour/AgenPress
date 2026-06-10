import { useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { uploadFile } from '../api';

export default function FileUpload( { onUploaded } ) {
	const inputRef = useRef( null );
	const [ uploading, setUploading ] = useState( false );

	const handleClick = () => {
		inputRef.current?.click();
	};

	const handleChange = async ( e ) => {
		const file = e.target.files?.[ 0 ];
		if ( ! file ) return;

		setUploading( true );

		try {
			const result = await uploadFile( file );
			onUploaded( result );
		} catch ( err ) {
			// eslint-disable-next-line no-alert
			alert( err.message || __( 'Upload failed.', 'agenpress' ) );
		} finally {
			setUploading( false );
			if ( inputRef.current ) {
				inputRef.current.value = '';
			}
		}
	};

	return (
		<>
			<input
				ref={ inputRef }
				type="file"
				style={ { display: 'none' } }
				onChange={ handleChange }
				accept="image/*,.pdf,.doc,.docx"
			/>
			<button
				className="ap-btn ap-btn-secondary"
				onClick={ handleClick }
				disabled={ uploading }
				title={ __( 'Upload file', 'agenpress' ) }
			>
				{ uploading ? '...' : '📎' }
			</button>
		</>
	);
}
