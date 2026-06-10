import apiFetch from '@wordpress/api-fetch';

const data = window.agenpressElementorData || {};

/**
 * Build a full REST URL so requests work when Elementor overrides
 * apiFetch's default root URL middleware.
 *
 * @param {string} endpoint Path under agenpress/v1.
 * @return {string}
 */
function restUrl( endpoint ) {
	const base = ( data.apiUrl || '/wp-json/agenpress/v1/' ).replace( /\/$/, '' );
	return `${ base }/${ String( endpoint ).replace( /^\//, '' ) }`;
}

export async function uploadFile( file ) {
	const formData = new FormData();
	formData.append( 'file', file );

	const response = await fetch( restUrl( 'upload' ), {
		method: 'POST',
		headers: {
			'X-WP-Nonce': data.nonce || '',
		},
		body: formData,
		credentials: 'same-origin',
	} );

	const json = await response.json();

	if ( ! json.success ) {
		throw new Error( json.error?.message || 'Upload failed' );
	}

	return json.data;
}

export async function sendElementorMessage( message, conversationId, context, attachments = [] ) {
	const res = await apiFetch( {
		url: restUrl( 'chat/elementor' ),
		method: 'POST',
		data: {
			message,
			conversation_id: conversationId,
			context,
			attachments,
		},
	} );
	return res.data;
}

export async function confirmElementorAction( pendingId, conversationId ) {
	const res = await apiFetch( {
		url: restUrl( 'chat/elementor/confirm' ),
		method: 'POST',
		data: {
			pending_id: pendingId,
			conversation_id: conversationId,
		},
	} );
	return res.data;
}
