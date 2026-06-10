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

export async function sendSalesMessage( message, conversationId ) {
	const res = await apiFetch( {
		url: restUrl( 'sales/chat' ),
		method: 'POST',
		data: { message, conversation_id: conversationId },
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
