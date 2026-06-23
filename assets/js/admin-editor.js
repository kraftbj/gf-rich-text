/* global jQuery, tinymce, GetSelectedField, SetFieldProperty, form */
( function( $ ) {
	'use strict';

	var EDITOR_ID = 'gf_rich_text_block_editor';

	function getEditor() {
		return ( window.tinymce && tinymce.get( EDITOR_ID ) ) || null;
	}

	function isOurField( field ) {
		return field && field.type === 'rich_text_block';
	}

	function loadContent( field ) {
		var content = field && field.content ? field.content : '';
		var editor = getEditor();
		if ( editor ) {
			editor.setContent( content );
		} else {
			$( '#' + EDITOR_ID ).val( content );
		}
	}

	function updatePreview( html ) {
		if ( ! window.GetSelectedField ) {
			return;
		}
		var field = GetSelectedField();
		if ( ! isOurField( field ) ) {
			return;
		}
		$( '#field_' + field.id + ' .gf-rich-text-block--preview' ).html( html || '' );
	}

	function persist() {
		if ( ! window.GetSelectedField ) {
			return;
		}
		var field = GetSelectedField();
		if ( ! isOurField( field ) ) {
			return;
		}
		var editor = getEditor();
		var html;
		/* Bug 2 fix: when the Text tab is active, editor.isHidden() is true
		 * and the user's edits live in the raw textarea, not in TinyMCE's
		 * internal state.  Read the textarea in that case. */
		if ( editor && ! editor.isHidden() ) {
			html = editor.getContent();
		} else {
			html = $( '#' + EDITOR_ID ).val();
		}
		SetFieldProperty( 'content', html );
		updatePreview( html );
	}

	function bindEditorEvents() {
		var editor = getEditor();
		/* Bug 1 fix: use a per-instance property instead of a module-level
		 * flag so that a freshly re-initialized TinyMCE instance (e.g. after
		 * the media modal closes) gets re-bound automatically. */
		if ( ! editor || editor._gfRtbBound ) {
			return;
		}
		editor._gfRtbBound = true;
		editor.on( 'change keyup SetContent ExecCommand', persist );
	}

	function populateMergeTags() {
		var $select = $( '#gf_rtb_mergetag_select' );
		if ( ! $select.length || typeof form === 'undefined' || ! form.fields ) {
			return;
		}
		// Keep the placeholder option; rebuild the rest from the current form.
		$select.find( 'option:gt(0)' ).remove();
		form.fields.forEach( function( f ) {
			if ( f.type === 'rich_text_block' || ! f.label ) {
				return;
			}
			$select.append(
				$( '<option></option>' )
					.val( '{' + f.label + ':' + f.id + '}' )
					.text( f.label )
			);
		} );
	}

	function insertMergeTag( tag ) {
		if ( ! tag ) {
			return;
		}
		var editor = getEditor();
		if ( editor ) {
			editor.insertContent( tag );
		} else {
			var $ta = $( '#' + EDITOR_ID );
			$ta.val( ( $ta.val() || '' ) + tag );
		}
		persist();
	}

	/* wp_editor() is rendered inside Gravity Forms' field-settings panel, which
	 * is hidden at page load. A TinyMCE instance built inside a hidden container
	 * initializes in a broken state: it reports ready but its iframe cannot take
	 * keyboard focus, so the user cannot type. Merely switching tabs does not fix
	 * this. When our field's panel becomes visible, destroy any existing instance
	 * and recreate it so the iframe is built in a focusable, visible context, then
	 * run cb once the fresh instance has initialized. */
	function ensureEditor( cb ) {
		if ( ! window.tinymce ) {
			cb();
			return;
		}
		if ( tinymce.get( EDITOR_ID ) ) {
			tinymce.execCommand( 'mceRemoveEditor', false, EDITOR_ID );
		}
		tinymce.execCommand( 'mceAddEditor', false, EDITOR_ID );
		var editor = getEditor();
		if ( editor && ! editor.initialized ) {
			editor.on( 'init', cb );
		} else {
			cb();
		}
	}

	$( document ).on( 'gform_load_field_settings', function( event, field ) {
		if ( ! isOurField( field ) ) {
			return;
		}
		ensureEditor( function() {
			loadContent( field );
			bindEditorEvents();
		} );
		populateMergeTags();
	} );

	$( document ).on( 'change', '#gf_rtb_mergetag_select', function() {
		insertMergeTag( $( this ).val() );
		$( this ).val( '' );
	} );

	/* Bug 2 fix: persist Text-tab edits live.  When the Text tab is active
	 * TinyMCE is hidden and the user edits the raw textarea directly, so the
	 * TinyMCE change/keyup events never fire.  Delegated binding survives
	 * panel re-rendering the same way the merge-tag handler does. */
	$( document ).on( 'input keyup', '#' + EDITOR_ID, persist );
} )( jQuery );
