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

if ( ! function_exists( 'apply_filters' ) ) {
    /**
     * Test double: returns the default value unchanged.
     *
     * @param string $hook  Hook name.
     * @param mixed  $value Default value.
     * @return mixed
     */
    function apply_filters( $hook, $value ) {
        return $value;
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
         * Records the [url_encode, esc_html, nl2br, format] of the last call so
         * tests can assert the renderer passes safe explicit arguments.
         *
         * @var array
         */
        public static $last_replace_args = array();

        /**
         * Stub replacement that marks the text. Accepts the full GF argument
         * list so callers passing explicit url_encode/esc_html/nl2br/format work.
         *
         * @param string $text       Text.
         * @param array  $form       Form.
         * @param mixed  $entry      Entry.
         * @param bool   $url_encode URL-encode flag.
         * @param bool   $esc_html   Escape-HTML flag.
         * @param bool   $nl2br      nl2br flag.
         * @param string $format     Output format.
         * @return string
         */
        public static function replace_variables( $text, $form = array(), $entry = null, $url_encode = false, $esc_html = true, $nl2br = true, $format = 'html' ) {
            self::$last_replace_args = array( $url_encode, $esc_html, $nl2br, $format );
            return 'REPLACED::' . $text;
        }
    }
}

require_once __DIR__ . '/../includes/class-content-sanitizer.php';
require_once __DIR__ . '/../includes/class-content-renderer.php';
