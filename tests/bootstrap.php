<?php
/**
 * Test bootstrap: minimal WP/GF stubs so pure-logic classes run without WordPress.
 *
 * @package GF_Rich_Text_Block
 */

if ( ! function_exists( 'wp_kses_post' ) ) {
    /**
     * Test double. Prefixes input so tests can assert it ran.
     *
     * @param string $content Content.
     * @return string
     */
    function wp_kses_post( $content ) {
        return 'KSES::' . $content;
    }
}

if ( ! function_exists( 'do_shortcode' ) ) {
    /**
     * Test double for shortcode expansion.
     *
     * @param string $content Content.
     * @return string
     */
    function do_shortcode( $content ) {
        return 'SC::' . $content;
    }
}

if ( ! class_exists( 'GFCommon' ) ) {
    /**
     * Test double for Gravity Forms merge-tag replacement.
     */
    class GFCommon {
        /**
         * Stub replacement that marks the text.
         *
         * @param string $text   Text.
         * @param array  $form   Form.
         * @param mixed  $entry  Entry.
         * @return string
         */
        public static function replace_variables( $text, $form = array(), $entry = null ) {
            return 'REPLACED::' . $text;
        }
    }
}

require_once __DIR__ . '/../includes/class-content-sanitizer.php';
// require_once __DIR__ . '/../includes/class-content-renderer.php'; // Task 3 uncomments this line.
