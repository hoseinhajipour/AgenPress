/**
 * Reload Elementor editor state from the server after AI changes.
 *
 * Elementor's preview uses in-memory document data. reloadPreview() alone
 * does not pick up changes saved via the REST API until we re-import elements.
 *
 * @param {number} postId Document post ID.
 * @return {Promise<void>}
 */
export async function refreshElementorDocument( postId ) {
	const elementor = window.elementor;
	const $e = window.$e;
	const resolvedId = postId || elementor?.config?.document?.id;

	if ( ! resolvedId || ! elementor ) {
		return;
	}

	const data = window.agenpressElementorData || {};
	let elements = null;

	try {
		const response = await fetch(
			`${ String( data.apiUrl || '' ).replace( /\/$/, '' ) }/elementor/documents/${ resolvedId }/elements`,
			{
				headers: {
					'X-WP-Nonce': data.nonce || '',
				},
				credentials: 'same-origin',
			}
		);

		const json = await response.json();

		if ( json?.success && Array.isArray( json?.data?.elements ) ) {
			elements = json.data.elements;
		}
	} catch ( error ) {
		// Fall through to other strategies.
	}

	if ( elements && $e?.run ) {
		try {
			await $e.run( 'document/elements/import', { elements } );
			elementor.reloadPreview?.();
			return;
		} catch ( error ) {
			// Try the next strategy.
		}
	}

	if ( window.elementorCommon?.ajax && $e?.run ) {
		try {
			const config = await new Promise( ( resolve, reject ) => {
				window.elementorCommon.ajax.addRequest( 'get_document_config', {
					data: { id: resolvedId },
					success: resolve,
					error: reject,
				} );
			} );

			const configElements = config?.config?.elements;

			if ( Array.isArray( configElements ) ) {
				await $e.run( 'document/elements/import', { elements: configElements } );
				elementor.reloadPreview?.();
				return;
			}
		} catch ( error ) {
			// Try the next strategy.
		}
	}

	if ( $e?.run ) {
		try {
			await $e.run( 'editor/documents/close', { id: resolvedId, confirm: false } );
			await $e.run( 'editor/documents/open', { id: resolvedId } );
			return;
		} catch ( error ) {
			// Fall back to preview reload only.
		}
	}

	elementor.reloadPreview?.();
}
