/* global jQuery, tinymce, GetSelectedField, SetFieldProperty, form */

/*
 * Drives the TinyMCE editor that lives inside Gravity Forms' single, shared
 * field-settings panel. Because there is one editor element ('gf_rich_text_block_editor')
 * reused for every Rich Text Block field, this script swaps each field's content
 * in and out of that one editor as fields are selected.
 *
 * Two structural hazards are handled here:
 *  1. The panel is hidden at page load, so a TinyMCE instance built then cannot
 *     take keyboard focus. We fully recreate the editor (mceRemoveEditor +
 *     mceAddEditor) when a field is selected, so it is built in a visible,
 *     focusable context.
 *  2. Content is pushed into the form model only on editor events, so switching
 *     fields or saving must flush the outgoing field first, and stale/async
 *     callbacks must not write into the wrong field. A generation token, an
 *     isLoading guard, and a per-field "loaded id" make those operations safe.
 */
( function( $ ) {
	'use strict';

	var EDITOR_ID = 'gf_rich_text_block_editor';
	var MERGE_TAG_SELECT = 'gf_rtb_mergetag_select';

	var loadSeq = 0;          // Bumped on every field selection; discards stale async callbacks.
	var loadedFieldId = null; // Id of the field whose content the editor currently holds.
	var isLoading = false;    // True while content is loaded programmatically (suppresses persist).

	function getEditor() {
		return ( window.tinymce && tinymce.get( EDITOR_ID ) ) || null;
	}

	function isOurField( field ) {
		return field && field.type === 'rich_text_block';
	}

	function previewFor( fieldId ) {
		return $( '#field_' + fieldId + ' .gf-rich-text-block--preview' );
	}

	/*
	 * Write the editor's content to the currently-loaded field. Bound to editor
	 * events. Bails during a programmatic load and never writes unless the loaded
	 * field is still the selected field, so a late or teardown event cannot leak
	 * one field's content into another.
	 */
	function persist() {
		if ( isLoading || loadedFieldId === null ) {
			return;
		}
		var selected = window.GetSelectedField ? GetSelectedField() : null;
		if ( ! selected || ! isOurField( selected ) || String( selected.id ) !== String( loadedFieldId ) ) {
			return;
		}
		var editor = getEditor();
		if ( ! editor || ! editor.initialized ) {
			return;
		}
		var html = editor.getContent();
		SetFieldProperty( 'content', html );
		previewFor( loadedFieldId ).html( html );
	}

	/*
	 * Save the currently-loaded field's content to the form model BEFORE the
	 * editor is torn down or a new field is loaded. Writes by field id (the GF
	 * selection may have already moved on), so no edits are lost on field switch
	 * or save.
	 */
	function flush() {
		if ( loadedFieldId === null || typeof form === 'undefined' || ! form.fields ) {
			return;
		}
		var editor = getEditor();
		if ( ! editor || ! editor.initialized ) {
			return;
		}
		var html = editor.getContent();
		for ( var i = 0; i < form.fields.length; i++ ) {
			if ( String( form.fields[ i ].id ) === String( loadedFieldId ) ) {
				form.fields[ i ].content = html;
				previewFor( loadedFieldId ).html( html );
				return;
			}
		}
	}

	function bindEditor( editor ) {
		if ( ! editor || editor._gfRtbBound ) {
			return;
		}
		editor._gfRtbBound = true;
		// 'blur' flushes content when focus leaves the editor (e.g. clicking Save Form).
		editor.on( 'change keyup SetContent blur', persist );
	}

	function unbindEditor( editor ) {
		if ( editor && editor._gfRtbBound ) {
			editor.off( 'change keyup SetContent blur', persist );
			editor._gfRtbBound = false;
		}
	}

	/*
	 * Recreate the editor in the now-visible panel, then run cb(editor) once the
	 * fresh instance is ready. mceAddEditor can resolve asynchronously, so we wait
	 * on the EditorManager 'AddEditor' event instead of reading tinymce.get()
	 * inline (which can return null immediately after the command).
	 */
	function recreateEditor( cb ) {
		if ( ! window.tinymce ) {
			return;
		}
		var existing = tinymce.get( EDITOR_ID );
		if ( existing ) {
			unbindEditor( existing );
			tinymce.execCommand( 'mceRemoveEditor', false, EDITOR_ID );
		}
		var onAdd = function( e ) {
			if ( ! e.editor || e.editor.id !== EDITOR_ID ) {
				return;
			}
			tinymce.off( 'AddEditor', onAdd );
			if ( e.editor.initialized ) {
				cb( e.editor );
			} else {
				e.editor.on( 'init', function() {
					cb( e.editor );
				} );
			}
		};
		tinymce.on( 'AddEditor', onAdd );
		tinymce.execCommand( 'mceAddEditor', false, EDITOR_ID );
	}

	function populateMergeTags() {
		var $select = $( '#' + MERGE_TAG_SELECT );
		if ( ! $select.length || typeof form === 'undefined' || ! form.fields ) {
			return;
		}
		// Keep the placeholder option; rebuild the rest from the current form.
		$select.find( 'option:gt(0)' ).remove();
		form.fields.forEach( function( f ) {
			// Skip our own fields and labels that would produce a malformed merge tag.
			if ( f.type === 'rich_text_block' || ! f.label || /[{}:]/.test( f.label ) ) {
				return;
			}
			$select.append(
				$( '<option></option>' ).val( '{' + f.label + ':' + f.id + '}' ).text( f.label )
			);
		} );
	}

	function insertMergeTag( tag ) {
		var editor = getEditor();
		if ( tag && editor && editor.initialized ) {
			editor.insertContent( tag );
			persist();
		}
	}

	$( document ).on( 'gform_load_field_settings', function( event, field ) {
		// Flush whatever was being edited before anything tears the editor down.
		flush();

		if ( ! isOurField( field ) ) {
			loadedFieldId = null;
			return;
		}

		var mySeq = ++loadSeq;
		var fieldId = field.id;
		var content = field.content ? field.content : '';

		populateMergeTags();

		recreateEditor( function( editor ) {
			// A newer selection superseded this one; discard this callback.
			if ( mySeq !== loadSeq ) {
				return;
			}
			loadedFieldId = fieldId;
			isLoading = true;
			editor.setContent( content );
			isLoading = false;
			previewFor( fieldId ).html( content );
			bindEditor( editor );
		} );
	} );

	$( document ).on( 'change', '#' + MERGE_TAG_SELECT, function() {
		insertMergeTag( $( this ).val() );
		$( this ).val( '' );
	} );
} )( jQuery );
