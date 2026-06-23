<?php
/**
 * Front-end content rendering (merge tags + optional shortcodes).
 *
 * @package GF_Rich_Text_Block
 */

namespace GF_Rich_Text_Block;

/**
 * Processes authored content for front-end display.
 */
class Content_Renderer {

	/**
	 * Render authored content for display.
	 *
	 * @param string     $content        Stored (already-sanitized) HTML.
	 * @param array      $form           Gravity Forms form array.
	 * @param array|null $entry          Entry array, or null when no submission exists yet.
	 * @param bool       $run_shortcodes Whether to expand WordPress shortcodes.
	 * @return string Processed inner HTML.
	 */
	public static function render( string $content, array $form, $entry, bool $run_shortcodes ): string {
		/*
		 * Expand shortcodes first, from the trusted authored template, so that
		 * submitter-supplied merge-tag values cannot inject executable shortcode
		 * syntax that runs after replacement.
		 */
		if ( $run_shortcodes ) {
			$content = do_shortcode( $content );
		}

		/*
		 * Replace merge tags with explicit arguments: url_encode=false,
		 * esc_html=true (so submitter-supplied field values are HTML-escaped
		 * while the authored markup is preserved), nl2br=false (authored HTML is
		 * already structured; nl2br would inject spurious <br> tags), format=html.
		 */
		return \GFCommon::replace_variables( $content, $form, $entry, false, true, false, 'html' );
	}
}
