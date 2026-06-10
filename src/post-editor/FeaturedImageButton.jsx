import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import FeaturedImageModal from './FeaturedImageModal';

function applyFeaturedImageClassic( attachmentId, url ) {
	if ( window.wp?.media?.featuredImage?.set ) {
		window.wp.media.featuredImage.set( attachmentId );
		return;
	}

	const thumbnailField = document.getElementById( '_thumbnail_id' );
	if ( thumbnailField ) {
		thumbnailField.value = String( attachmentId );
	}

	const container = document.getElementById( 'postimagediv' );
	const inside = container?.querySelector( '.inside' );
	if ( inside && url ) {
		const existing = inside.querySelector( '#postimagediv-preview' );
		if ( existing ) {
			existing.innerHTML = `<img src="${ url }" style="max-width:100%;height:auto;" alt="" />`;
		}
	}

	const removeLink = inside?.querySelector( '#remove-post-thumbnail' );
	if ( removeLink ) {
		removeLink.style.display = '';
	}
}

function BlockEditorButton( { postId } ) {
	const [ modalOpen, setModalOpen ] = useState( false );
	const { editPost } = useDispatch( 'core/editor' );

	const handleApplied = ( result ) => {
		if ( editPost && result?.attachment_id ) {
			editPost( { featured_media: result.attachment_id } );
		}
	};

	return (
		<>
			<p className="ap-fi-button-wrap">
				<button
					type="button"
					className="button ap-fi-generate-btn"
					onClick={ () => setModalOpen( true ) }
				>
					{ __( 'AI Generate image', 'agenpress' ) }
				</button>
			</p>
			<FeaturedImageModal
				postId={ postId }
				isOpen={ modalOpen }
				onClose={ () => setModalOpen( false ) }
				onApplied={ handleApplied }
			/>
		</>
	);
}

function ClassicEditorButton( { postId } ) {
	const [ modalOpen, setModalOpen ] = useState( false );

	const handleApplied = ( result ) => {
		if ( result?.attachment_id ) {
			applyFeaturedImageClassic( result.attachment_id, result.url );
		}
	};

	return (
		<>
			<p className="ap-fi-button-wrap">
				<button
					type="button"
					className="button ap-fi-generate-btn"
					onClick={ () => setModalOpen( true ) }
				>
					{ __( 'AI Generate image', 'agenpress' ) }
				</button>
			</p>
			<FeaturedImageModal
				postId={ postId }
				isOpen={ modalOpen }
				onClose={ () => setModalOpen( false ) }
				onApplied={ handleApplied }
			/>
		</>
	);
}

export default function FeaturedImageButton( { editorType, postId: propPostId } ) {
	const blockPostId = useSelect( ( select ) => {
		if ( editorType !== 'block' ) {
			return 0;
		}
		return select( 'core/editor' )?.getCurrentPostId?.() || 0;
	}, [ editorType ] );

	const postId = editorType === 'block' ? blockPostId : propPostId;

	if ( editorType === 'block' ) {
		return <BlockEditorButton postId={ postId } />;
	}

	return <ClassicEditorButton postId={ postId } />;
}
