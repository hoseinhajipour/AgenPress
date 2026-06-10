/**
 * Read Elementor editor selection state.
 *
 * @return {object} Selection context for API requests.
 */
export function getElementorSelection() {
	const postId = window.elementor?.config?.document?.id || 0;
	const context = { post_id: postId };

	try {
		const preview = window.elementor?.getPreviewView?.();
		const model = preview?.getSelectedModel?.();

		if ( model ) {
			context.element_id = model.get( 'id' );
			context.el_type = model.get( 'elType' );
			const widgetType = model.get( 'widgetType' );
			if ( widgetType ) {
				context.widget_type = widgetType;
			}
			return context;
		}

		if ( window.$e?.components ) {
			const selection = window.$e.components.get( 'selection' );
			const elements = selection?.getElements?.() || [];

			if ( elements.length > 0 ) {
				const first = elements[ 0 ];
				context.element_id = first.id || first.get?.( 'id' );
				context.el_type = first.elType || first.get?.( 'elType' );
				const wt = first.widgetType || first.get?.( 'widgetType' );
				if ( wt ) {
					context.widget_type = wt;
				}
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

	const onElementorInit = () => {
		poll();
		if ( window.elementor?.channels?.editor ) {
			window.elementor.channels.editor.on( 'change', poll );
		}
	};

	if ( window.elementor ) {
		onElementorInit();
	} else {
		window.jQuery( window ).on( 'elementor:init', onElementorInit );
	}

	return () => {
		clearInterval( interval );
		if ( window.elementor?.channels?.editor ) {
			window.elementor.channels.editor.off( 'change', poll );
		}
	};
}
