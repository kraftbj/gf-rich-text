<?php
/**
 * Rich Text Block field type.
 *
 * @package GF_Rich_Text_Block
 */

namespace GF_Rich_Text_Block;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A display-only Gravity Forms field that renders authored rich-text content.
 */
class Field extends \GF_Field {

	/**
	 * Field type identifier.
	 *
	 * @var string
	 */
	public $type = 'rich_text_block';

	/**
	 * Exclude this field from entries, notifications, and {all_fields}.
	 *
	 * @var bool
	 */
	public $displayOnly = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase -- GF core property name.

	/**
	 * Toolbox button title.
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'Rich Text Block', 'gf-rich-text' );
	}

	/**
	 * Toolbox button description.
	 *
	 * @return string
	 */
	public function get_form_editor_field_description() {
		return esc_attr__(
			'Add formatted content (headings, lists, links, images) for display within your form.',
			'gf-rich-text'
		);
	}

	/**
	 * Field icon.
	 *
	 * @return string
	 */
	public function get_form_editor_field_icon() {
		return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>';
	}

	/**
	 * Place the button in the Standard Fields group.
	 *
	 * @return array
	 */
	public function get_form_editor_button() {
		return array(
			'group' => 'standard_fields',
			'text'  => $this->get_form_editor_field_title(),
		);
	}

	/**
	 * Settings shown for this field in the form editor.
	 *
	 * @return array
	 */
	public function get_form_editor_field_settings() {
		return array(
			'label_setting',
			'rich_content_setting',
			'css_class_setting',
			'conditional_logic_field_setting',
		);
	}

	/**
	 * Enable conditional logic (visibility) for this field.
	 *
	 * @return bool
	 */
	public function is_conditional_logic_supported() {
		return true;
	}

	/**
	 * Render the field.
	 *
	 * @param array      $form  Form object.
	 * @param string     $value Field value (unused; display-only).
	 * @param array|null $entry Entry object, or null pre-submission.
	 * @return string
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {
		$content = isset( $this->content ) ? (string) $this->content : '';

		if ( $this->is_form_editor() ) {
			$preview = '' === trim( $content )
				? esc_html__( 'Use the field settings to add rich text content.', 'gf-rich-text' )
				: $content;

			return sprintf(
				'<div class="gf-rich-text-block gf-rich-text-block--preview">%s</div>',
				$preview
			);
		}

		$run_shortcodes = (bool) apply_filters( 'gf_rich_text_block_run_shortcodes', false, $this, $form, $entry );
		$rendered       = Content_Renderer::render( $content, $form, $entry, $run_shortcodes );

		return sprintf( '<div class="gf-rich-text-block">%s</div>', $rendered );
	}

	/**
	 * Sanitize field settings when the form is saved.
	 *
	 * Mirrors core behavior: authors with the `unfiltered_html` capability keep
	 * raw markup; everyone else has content run through wp_kses_post(). This is
	 * the same security posture as Gravity Forms' own HTML field.
	 *
	 * Gravity Forms invokes this via GFFormsModel::sanitize_settings() on the
	 * form-editor save path, which is the only route a user reaches through the
	 * UI. Programmatic routes (GFAPI::add_form(), form import) do not run this
	 * and store content as given, but both require the `gravityforms_edit_forms`
	 * capability, so they are already privileged operations.
	 */
	public function sanitize_settings() {
		parent::sanitize_settings();

		$can_unfiltered = function_exists( 'current_user_can' ) && current_user_can( 'unfiltered_html' );

		if ( isset( $this->content ) ) {
			$this->content = Content_Sanitizer::sanitize( (string) $this->content, (bool) $can_unfiltered );
		}
	}
}
