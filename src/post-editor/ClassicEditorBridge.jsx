import { useEffect, useState } from '@wordpress/element';
import GenerateTextModal from './GenerateTextModal';
import InsertImageModal from './InsertImageModal';

function insertIntoEditor( editorId, content ) {
	const editor = window.tinymce?.get( editorId );

	if ( editor && ! editor.isHidden() ) {
		editor.insertContent( content );
		return;
	}

	const textarea = document.getElementById( editorId );
	if ( textarea ) {
		const start = textarea.selectionStart ?? textarea.value.length;
		const end = textarea.selectionEnd ?? textarea.value.length;
		const before = textarea.value.slice( 0, start );
		const after = textarea.value.slice( end );
		textarea.value = before + content + after;
		textarea.selectionStart = textarea.selectionEnd = start + content.length;
		textarea.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	}
}

export default function ClassicEditorBridge() {
	const [ textModal, setTextModal ] = useState( {
		open: false,
		editorId: '',
		selection: '',
	} );
	const [ imageModal, setImageModal ] = useState( {
		open: false,
		editorId: '',
	} );

	useEffect( () => {
		const onText = ( event ) => {
			setTextModal( {
				open: true,
				editorId: event.detail?.editorId || 'content',
				selection: event.detail?.selection || '',
			} );
		};

		const onImage = ( event ) => {
			setImageModal( {
				open: true,
				editorId: event.detail?.editorId || 'content',
			} );
		};

		document.addEventListener( 'agenpress:open-text-modal', onText );
		document.addEventListener( 'agenpress:open-image-modal', onImage );

		return () => {
			document.removeEventListener( 'agenpress:open-text-modal', onText );
			document.removeEventListener( 'agenpress:open-image-modal', onImage );
		};
	}, [] );

	return (
		<>
			<GenerateTextModal
				isOpen={ textModal.open }
				selection={ textModal.selection }
				onClose={ () =>
					setTextModal( { open: false, editorId: '', selection: '' } )
				}
				onInsert={ ( content ) =>
					insertIntoEditor( textModal.editorId, content )
				}
			/>
			<InsertImageModal
				isOpen={ imageModal.open }
				onClose={ () => setImageModal( { open: false, editorId: '' } ) }
				onInsert={ ( content ) =>
					insertIntoEditor( imageModal.editorId, content )
				}
			/>
		</>
	);
}
