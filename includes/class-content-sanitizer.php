<?php
/**
 * Save-time content sanitization.
 *
 * @package GF_Rich_Text_Block
 */

namespace GF_Rich_Text_Block;

/**
 * Sanitizes authored rich-text content based on the author's capability.
 */
class Content_Sanitizer {

	/**
	 * Sanitize content for storage.
	 *
	 * @param string $content                 Raw authored HTML.
	 * @param bool   $can_use_unfiltered_html Whether the author may store unfiltered HTML.
	 * @return string Sanitized content.
	 */
	public static function sanitize( string $content, bool $can_use_unfiltered_html ): string {
		if ( $can_use_unfiltered_html ) {
			return $content;
		}

		return wp_kses_post( $content );
	}
}
