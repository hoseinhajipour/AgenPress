import apiFetch from '@wordpress/api-fetch';

const data = window.agenpressPostEditorData || {};

/**
 * Build a full REST URL so requests work when other plugins override
 * apiFetch's default root URL middleware.
 *
 * @param {string} endpoint Path under agenpress/v1.
 * @return {string}
 */
function restUrl( endpoint ) {
	const base = ( data.apiUrl || '/wp-json/agenpress/v1/' ).replace( /\/$/, '' );
	return `${ base }/${ String( endpoint ).replace( /^\//, '' ) }`;
}

export async function generateImage( prompt, size ) {
	const res = await apiFetch( {
		url: restUrl( 'generate-image' ),
		method: 'POST',
		data: {
			prompt,
			size,
		},
	} );

	return res.data;
}

export async function setFeaturedImage( postId, attachmentId ) {
	const res = await apiFetch( {
		url: restUrl( `posts/${ postId }/featured-image` ),
		method: 'POST',
		data: {
			attachment_id: attachmentId,
		},
	} );

	return res.data;
}

export async function generateText( prompt, context = '' ) {
	const res = await apiFetch( {
		url: restUrl( 'generate-text' ),
		method: 'POST',
		data: {
			prompt,
			context,
		},
	} );

	return res.data;
}
