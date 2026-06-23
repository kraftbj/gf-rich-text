/* global jQuery, tinymce, GetSelectedField, SetFieldProperty, form */
( function( $ ) {
	'use strict';

	var EDITOR_ID = 'gf_rich_text_block_editor';
	var bound = false;

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
		var html = editor ? editor.getContent() : $( '#' + EDITOR_ID ).val();
		SetFieldProperty( 'content', html );
		updatePreview( html );
	}

	function bindEditorEvents() {
		var editor = getEditor();
		if ( ! editor || bound ) {
			return;
		}
		bound = true;
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

	$( document ).on( 'gform_load_field_settings', function( event, field ) {
		if ( ! isOurField( field ) ) {
			return;
		}
		loadContent( field );
		bindEditorEvents();
		populateMergeTags();
	} );

	$( document ).on( 'change', '#gf_rtb_mergetag_select', function() {
		insertMergeTag( $( this ).val() );
		$( this ).val( '' );
	} );
} )( jQuery );
