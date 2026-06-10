import apiFetch from '@wordpress/api-fetch';

const BASE = '';

export async function getSettings() {
	const res = await apiFetch( { path: `${ BASE }/settings` } );
	return res.data;
}

export async function updateSettings( data ) {
	const res = await apiFetch( {
		path: `${ BASE }/settings`,
		method: 'PUT',
		data,
	} );
	return res.data;
}

export async function getConversations( module = null ) {
	const query = module ? `?module=${ module }` : '';
	const res = await apiFetch( { path: `${ BASE }/conversations${ query }` } );
	return res.data;
}

export async function getConversation( id ) {
	const res = await apiFetch( { path: `${ BASE }/conversations/${ id }` } );
	return res.data;
}

export async function createConversation( module, title = '' ) {
	const res = await apiFetch( {
		path: `${ BASE }/conversations`,
		method: 'POST',
		data: { module, title },
	} );
	return res.data;
}

export async function deleteConversation( id ) {
	return apiFetch( {
		path: `${ BASE }/conversations/${ id }`,
		method: 'DELETE',
	} );
}

export async function sendMessage( module, message, conversationId = 0, attachments = [] ) {
	const res = await apiFetch( {
		path: `${ BASE }/chat/${ module }`,
		method: 'POST',
		data: {
			message,
			conversation_id: conversationId,
			attachments,
		},
	} );
	return res.data;
}

export async function confirmAction( module, pendingId, conversationId ) {
	const res = await apiFetch( {
		path: `${ BASE }/chat/${ module }/confirm`,
		method: 'POST',
		data: {
			pending_id: pendingId,
			conversation_id: conversationId,
		},
	} );
	return res.data;
}

export async function getTasks( status = null ) {
	const query = status ? `?status=${ status }` : '';
	const res = await apiFetch( { path: `${ BASE }/tasks${ query }` } );
	return res.data;
}

export async function getTask( id ) {
	const res = await apiFetch( { path: `${ BASE }/tasks/${ id }` } );
	return res.data;
}

export async function getTaskTemplates() {
	const res = await apiFetch( { path: `${ BASE }/tasks/templates` } );
	return res.data;
}

export async function createTask( title, description = '', module = 'admin', template = '', params = {} ) {
	const res = await apiFetch( {
		path: `${ BASE }/tasks`,
		method: 'POST',
		data: { title, description, module, template, params },
	} );
	return res.data;
}

export async function cancelTask( id ) {
	const res = await apiFetch( {
		path: `${ BASE }/tasks/${ id }/cancel`,
		method: 'POST',
	} );
	return res.data;
}

export async function retryTask( id ) {
	const res = await apiFetch( {
		path: `${ BASE }/tasks/${ id }/retry`,
		method: 'POST',
	} );
	return res.data;
}

export async function rerunTask( id ) {
	const res = await apiFetch( {
		path: `${ BASE }/tasks/${ id }/rerun`,
		method: 'POST',
	} );
	return res.data;
}

export async function toggleTaskPause( id ) {
	const res = await apiFetch( {
		path: `${ BASE }/tasks/${ id }/pause`,
		method: 'POST',
	} );
	return res.data;
}

export async function deleteTask( id ) {
	return apiFetch( {
		path: `${ BASE }/tasks/${ id }`,
		method: 'DELETE',
	} );
}

export async function getMemory( category = null, search = '' ) {
	const params = new URLSearchParams();
	if ( category ) params.set( 'category', category );
	if ( search ) params.set( 'search', search );
	const query = params.toString() ? `?${ params }` : '';
	const res = await apiFetch( { path: `${ BASE }/memory${ query }` } );
	return res.data;
}

export async function createMemory( category, keyName, value ) {
	const res = await apiFetch( {
		path: `${ BASE }/memory`,
		method: 'POST',
		data: { category, key_name: keyName, value },
	} );
	return res.data;
}

export async function updateMemory( id, data ) {
	const res = await apiFetch( {
		path: `${ BASE }/memory/${ id }`,
		method: 'PUT',
		data,
	} );
	return res.data;
}

export async function deleteMemory( id ) {
	return apiFetch( {
		path: `${ BASE }/memory/${ id }`,
		method: 'DELETE',
	} );
}

export async function searchMemory( query, category = null, limit = 10 ) {
	const params = new URLSearchParams( { query } );
	if ( category ) params.set( 'category', category );
	if ( limit ) params.set( 'limit', String( limit ) );
	const res = await apiFetch( { path: `${ BASE }/memory/search?${ params }` } );
	return res.data;
}

export async function extractBrandMemory() {
	const res = await apiFetch( {
		path: `${ BASE }/memory/extract-brand`,
		method: 'POST',
	} );
	return res.data;
}

export async function reindexMemory() {
	const res = await apiFetch( {
		path: `${ BASE }/memory/reindex`,
		method: 'POST',
	} );
	return res.data;
}

export async function getInbox() {
	const res = await apiFetch( { path: `${ BASE }/inbox` } );
	return res.data;
}

export async function getInboxConversation( id ) {
	const res = await apiFetch( { path: `${ BASE }/inbox/${ id }` } );
	return res.data;
}

export async function resolveInboxConversation( id ) {
	const res = await apiFetch( {
		path: `${ BASE }/inbox/${ id }/resolve`,
		method: 'POST',
	} );
	return res.data;
}

export async function assignInboxConversation( id, userId ) {
	const res = await apiFetch( {
		path: `${ BASE }/inbox/${ id }/assign`,
		method: 'POST',
		data: { user_id: userId },
	} );
	return res.data;
}

export async function getInboxTeam() {
	const res = await apiFetch( { path: `${ BASE }/inbox/team` } );
	return res.data;
}

export async function getAnalytics( days = 30 ) {
	const res = await apiFetch( { path: `${ BASE }/analytics?days=${ days }` } );
	return res.data;
}

export async function getWorkflows() {
	const res = await apiFetch( { path: `${ BASE }/workflows` } );
	return res.data;
}

export async function createWorkflow( title, steps ) {
	const res = await apiFetch( {
		path: `${ BASE }/workflows`,
		method: 'POST',
		data: { title, steps, enabled: true, trigger_type: 'manual' },
	} );
	return res.data;
}

export async function runWorkflow( id ) {
	const res = await apiFetch( {
		path: `${ BASE }/workflows/${ id }/run`,
		method: 'POST',
	} );
	return res.data;
}

export async function deleteWorkflow( id ) {
	return apiFetch( {
		path: `${ BASE }/workflows/${ id }`,
		method: 'DELETE',
	} );
}

export async function getApiKeys() {
	const res = await apiFetch( { path: `${ BASE }/api-keys` } );
	return res.data;
}

export async function createApiKey( name ) {
	const res = await apiFetch( {
		path: `${ BASE }/api-keys`,
		method: 'POST',
		data: { name },
	} );
	return res.data;
}

export async function deleteApiKey( id ) {
	return apiFetch( {
		path: `${ BASE }/api-keys/${ id }`,
		method: 'DELETE',
	} );
}

export async function orchestrateChat( message, conversationId = 0, specialist = '' ) {
	const res = await apiFetch( {
		path: `${ BASE }/orchestrate/chat`,
		method: 'POST',
		data: { message, conversation_id: conversationId, specialist },
	} );
	return res.data;
}

export async function uploadFile( file ) {
	const formData = new FormData();
	formData.append( 'file', file );

	const response = await fetch(
		`${ window.agenpressData.apiUrl }/upload`,
		{
			method: 'POST',
			headers: {
				'X-WP-Nonce': window.agenpressData.nonce,
			},
			body: formData,
			credentials: 'same-origin',
		}
	);

	const json = await response.json();

	if ( ! json.success ) {
		throw new Error( json.error?.message || 'Upload failed' );
	}

	return json.data;
}
