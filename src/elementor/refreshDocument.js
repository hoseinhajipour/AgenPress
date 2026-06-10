/**
 * Fetch the latest Elementor element tree from the server.
 *
 * @param {number} postId Document post ID.
 * @return {Promise<Array|null>}
 */
async function fetchDocumentElements( postId ) {
	const data = window.agenpressElementorData || {};
	const base = String( data.apiUrl || '' ).replace( /\/$/, '' );

	try {
		const response = await fetch(
			`${ base }/elementor/documents/${ postId }/elements`,
			{
				headers: {
					'X-WP-Nonce': data.nonce || '',
				},
				credentials: 'same-origin',
			}
		);

		const json = await response.json();

		if ( json?.success && Array.isArray( json?.data?.elements ) ) {
			return json.data.elements;
		}
	} catch ( error ) {
		// Fall through to other strategies.
	}

	return null;
}

/**
 * Update the open Elementor document in-place (no browser reload).
 *
 * @param {number} postId   Document post ID.
 * @param {Array}  elements Element tree from the server.
 * @return {Promise<boolean>}
 */
async function refreshInPlace( postId, elements ) {
	const elementor = window.elementor;
	const $e = window.$e;
	const document = elementor?.documents?.getCurrent?.();

	if ( ! elementor || ! document || document.id !== postId || ! Array.isArray( elements ) ) {
		return false;
	}

	if ( typeof elementor.createBackboneElementsCollection !== 'function' ) {
		return false;
	}

	try {
		if ( $e?.run ) {
			await $e.run( 'document/elements/deselect-all' );
		}

		document.config.elements = elements;

		if ( elementor.config?.document ) {
			elementor.config.document.elements = elements;
		}

		elementor.elements = elementor.createBackboneElementsCollection( elements );
		elementor.elementsModel = elementor.createBackboneElementsModel( elementor.elements );

		elementor.initPreviewView( document );
		document.container.view = elementor.getPreviewView();
		document.container.model.attributes.elements = elementor.elements;

		if ( typeof elementor.reloadPreview === 'function' ) {
			elementor.reloadPreview();
		}

		return true;
	} catch ( error ) {
		return false;
	}
}

/**
 * Reload the current document via Elementor's switch command.
 *
 * @param {number} postId Document post ID.
 * @return {Promise<boolean>}
 */
async function reloadViaDocumentSwitch( postId ) {
	const elementor = window.elementor;
	const $e = window.$e;

	if ( ! elementor?.documents?.invalidateCache || ! $e?.run ) {
		return false;
	}

	try {
		elementor.documents.invalidateCache( postId );

		await $e.run( 'editor/documents/switch', {
			id: postId,
			mode: 'discard',
			shouldScroll: false,
			shouldNavigateToDefaultRoute: false,
		} );

		return true;
	} catch ( error ) {
		return false;
	}
}

/**
 * Reload Elementor editor state from the server after AI changes.
 *
 * Elementor keeps document data in memory. After REST API saves, we must
 * re-import the element tree so new widgets appear without a browser refresh.
 *
 * @param {number} postId Document post ID.
 * @return {Promise<void>}
 */
export async function refreshElementorDocument( postId ) {
	const elementor = window.elementor;
	const resolvedId = postId || elementor?.config?.document?.id;

	if ( ! resolvedId || ! elementor ) {
		return;
	}

	const elements = await fetchDocumentElements( resolvedId );

	if ( elements && await refreshInPlace( resolvedId, elements ) ) {
		return;
	}

	if ( await reloadViaDocumentSwitch( resolvedId ) ) {
		return;
	}

	elementor.reloadPreview?.();
}
