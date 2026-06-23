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
		$content = \GFCommon::replace_variables( $content, $form, $entry );

		if ( $run_shortcodes ) {
			$content = do_shortcode( $content );
		}

		return $content;
	}
}
