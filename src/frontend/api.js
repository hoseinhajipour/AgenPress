import apiFetch from '@wordpress/api-fetch';

const data = window.agenpressChatData || {};

/**
 * Build a full REST URL so requests work even when other scripts
 * override apiFetch's default root URL middleware.
 *
 * @param {string} endpoint Path under agenpress/v1.
 * @return {string}
 */
function restUrl( endpoint ) {
	const base = ( data.apiUrl || '/wp-json/agenpress/v1/' ).replace( /\/$/, '' );
	return `${ base }/${ String( endpoint ).replace( /^\//, '' ) }`;
}

export async function getSalesSession() {
	const res = await apiFetch( {
		url: restUrl( 'sales/session' ),
		method: 'GET',
	} );
	return res.data;
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

export async function sendSalesMessage( message, conversationId, attachments = [] ) {
	const res = await apiFetch( {
		url: restUrl( 'sales/chat' ),
		method: 'POST',
		data: { message, conversation_id: conversationId, attachments },
	} );
	return res.data;
}

export async function escalateConversation( conversationId ) {
	const res = await apiFetch( {
		url: restUrl( 'sales/escalate' ),
		method: 'POST',
		data: { conversation_id: conversationId },
	} );
	return res.data;
}
