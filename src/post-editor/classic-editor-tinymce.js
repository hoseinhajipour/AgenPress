( function () {
	const data = window.agenpressPostEditorData || {};
	const labels = data.labels || {};

	function openModal( type, editor ) {
		document.dispatchEvent(
			new CustomEvent( `agenpress:open-${ type }-modal`, {
				detail: {
					editorId: editor.id,
					selection: editor.selection.getContent( { format: 'text' } ),
				},
			} )
		);
	}

	tinymce.PluginManager.add( 'agenpress_classic_editor', function ( editor ) {
		editor.addButton( 'agenpress_generate_text', {
			title: labels.generateText || 'Generate Text',
			text: labels.generateText || 'AI Text',
			onclick() {
				openModal( 'text', editor );
			},
		} );

		editor.addButton( 'agenpress_generate_image', {
			title: labels.generateImage || 'Generate Image',
			text: labels.generateImage || 'AI Image',
			onclick() {
				openModal( 'image', editor );
			},
		} );
	} );
}() );
