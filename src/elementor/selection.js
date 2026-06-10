/**
 * Map an Elementor container to selection context fields.
 *
 * @param {object} container Elementor editor container.
 * @return {object|null} Partial selection context or null.
 */
function containerToContext( container ) {
	if ( ! container ) {
		return null;
	}

	const model = container.model;
	const elementId = container.id || model?.get?.( 'id' );
	if ( ! elementId ) {
		return null;
	}

	const context = {
		element_id: elementId,
		el_type: container.type || model?.get?.( 'elType' ),
	};

	const widgetType = model?.get?.( 'widgetType' );
	if ( widgetType ) {
		context.widget_type = widgetType;
	}

	return context;
}

/**
 * Read Elementor editor selection state.
 *
 * @return {object} Selection context for API requests.
 */
export function getElementorSelection() {
	const postId = window.elementor?.config?.document?.id || 0;
	const context = { post_id: postId };

	try {
		const elements = window.elementor?.selection?.getElements?.() || [];
		if ( elements.length > 0 ) {
			const selected = containerToContext( elements[ 0 ] );
			if ( selected ) {
				return { ...context, ...selected };
			}
		}

		// Legacy fallback for very old Elementor versions.
		const preview = window.elementor?.getPreviewView?.();
		const model = preview?.getSelectedModel?.();
		if ( model ) {
			const legacy = containerToContext( { id: model.get( 'id' ), type: model.get( 'elType' ), model } );
			if ( legacy ) {
				return { ...context, ...legacy };
			}
		}
	} catch ( err ) {
		// Selection API unavailable — page context still useful.
	}

	return context;
}

/**
 * Subscribe to Elementor selection changes.
 *
 * @param {Function} callback Called with selection context.
 * @return {Function} Unsubscribe function.
 */
export function subscribeToSelection( callback ) {
	let lastKey = '';

	const poll = () => {
		const ctx = getElementorSelection();
		const key = `${ ctx.post_id }:${ ctx.element_id || '' }`;
		if ( key !== lastKey ) {
			lastKey = key;
			callback( ctx );
		}
	};

	const interval = setInterval( poll, 500 );
	const cleanups = [];

	const onElementorInit = () => {
		poll();

		if ( window.elementor?.channels?.editor ) {
			window.elementor.channels.editor.on( 'change', poll );
			cleanups.push( () => window.elementor.channels.editor.off( 'change', poll ) );
		}

		const selectionCommands = [
			'document/elements/select',
			'document/elements/deselect',
			'document/elements/deselect-all',
		];

		if ( window.$e?.routes?.on ) {
			const onRoute = ( component, route ) => {
				if ( selectionCommands.includes( route ) ) {
					poll();
				}
			};
			window.$e.routes.on( 'run:after', onRoute );
			cleanups.push( () => window.$e.routes.off( 'run:after', onRoute ) );
		}
	};

	if ( window.elementor ) {
		onElementorInit();
	} else {
		window.jQuery( window ).on( 'elementor:init', onElementorInit );
		cleanups.push( () => window.jQuery( window ).off( 'elementor:init', onElementorInit ) );
	}

	return () => {
		clearInterval( interval );
		cleanups.forEach( ( cleanup ) => cleanup() );
	};
}
