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
		$content = \GFCommon::replace_variables( $content, $form, $entry, false, true, false, 'html' );

		/*
		 * Process links: respect the target an author chose in the editor, default
		 * links with no target to a new tab (filterable -- for content inside a
		 * form, a new tab keeps a link click from losing the visitor's progress),
		 * and always add rel="noopener noreferrer" to new-tab links for security.
		 */
		$force_new_tab = (bool) apply_filters( 'gf_rich_text_block_links_new_tab', true );

		return self::process_links( $content, $force_new_tab );
	}

	/**
	 * Normalize link targets in rendered content. Author-set targets are kept;
	 * untargeted links default to a new tab when $force_new_tab is true; and any
	 * new-tab link gains rel="noopener noreferrer".
	 *
	 * @param string $html          Rendered HTML.
	 * @param bool   $force_new_tab Whether untargeted links should open in a new tab.
	 * @return string
	 */
	public static function process_links( string $html, bool $force_new_tab ): string {
		if ( false === stripos( $html, '<a ' ) ) {
			return $html;
		}

		return (string) preg_replace_callback(
			'/<a\s([^>]*?)\s*>/i',
			static function ( $matches ) use ( $force_new_tab ) {
				$attributes = rtrim( $matches[1] );

				if ( ! preg_match( '/\shref\s*=/i', ' ' . $attributes ) ) {
					return $matches[0];
				}

				if ( preg_match( '/\starget\s*=\s*(["\'])(.*?)\1/i', ' ' . $attributes, $target_match ) ) {
					$target = $target_match[2];
				} elseif ( $force_new_tab ) {
					$target      = '_blank';
					$attributes .= ' target="_blank"';
				} else {
					$target = '';
				}

				if ( '_blank' === $target && ! preg_match( '/\srel\s*=/i', ' ' . $attributes ) ) {
					$attributes .= ' rel="noopener noreferrer"';
				}

				return '<a ' . $attributes . '>';
			},
			$html
		);
	}
}
