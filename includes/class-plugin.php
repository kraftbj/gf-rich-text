<?php
/**
 * Plugin bootstrap.
 *
 * @package GF_Rich_Text_Block
 */

namespace GF_Rich_Text_Block;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the plugin's hooks and assets.
 */
class Plugin {

	const VERSION = '1.0.0';

	/**
	 * Plugin base URL (with trailing slash).
	 *
	 * @var string
	 */
	private string $url;

	/**
	 * Plugin base path (with trailing slash).
	 *
	 * @var string
	 */
	private string $path;

	/**
	 * Constructor.
	 *
	 * @param string $file Absolute path to the main plugin file.
	 */
	public function __construct( string $file ) {
		$this->url  = plugin_dir_url( $file );
		$this->path = plugin_dir_path( $file );
	}

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_action( 'gform_loaded', array( $this, 'register_field' ), 5 );
		add_action( 'gform_field_standard_settings', array( $this, 'render_rich_content_setting' ), 25, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'gform_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ), 10, 2 );
	}

	/**
	 * Render the Rich Content (TinyMCE) field setting.
	 *
	 * @param int $position Settings position marker.
	 * @param int $form_id  Form ID.
	 */
	public function render_rich_content_setting( $position, $form_id ): void {
		unset( $form_id );

		if ( 25 !== $position ) {
			return;
		}
		?>
		<li class="rich_content_setting field_setting">
			<label for="gf_rich_text_block_editor" class="section_label">
				<?php esc_html_e( 'Rich Content', 'gf-rich-text' ); ?>
			</label>
			<?php
			wp_editor(
				'',
				'gf_rich_text_block_editor',
				array(
					'media_buttons' => true,
					'wpautop'       => true,
					'quicktags'     => true,
					'editor_height' => 220,
					'tinymce'       => array(
						'toolbar1' => 'formatselect,bold,italic,bullist,numlist,link,alignleft,aligncenter,alignright',
						'toolbar2' => '',
					),
				)
			);
			?>
			<div class="gf-rtb-mergetags">
				<label for="gf_rtb_mergetag_select" class="screen-reader-text">
					<?php esc_html_e( 'Insert merge tag', 'gf-rich-text' ); ?>
				</label>
				<select id="gf_rtb_mergetag_select" class="gf-rtb-mergetag-select">
					<option value=""><?php esc_html_e( 'Insert merge tag…', 'gf-rich-text' ); ?></option>
				</select>
			</div>
		</li>
		<?php
	}

	/**
	 * Enqueue admin assets on the Gravity Forms form editor only.
	 */
	public function enqueue_admin_assets(): void {
		if ( ! class_exists( 'GFForms' ) || 'form_editor' !== \GFForms::get_page() ) {
			return;
		}

		wp_enqueue_editor();
		wp_enqueue_media();

		wp_enqueue_script(
			'gf-rich-text-block-admin',
			$this->url . 'assets/js/admin-editor.js',
			array( 'jquery' ),
			self::VERSION,
			true
		);

		wp_enqueue_style(
			'gf-rich-text-block-admin',
			$this->url . 'assets/css/admin-editor.css',
			array(),
			self::VERSION
		);
	}

	/**
	 * Enqueue front-end styles when a form is rendered.
	 *
	 * @param array $form    Form object.
	 * @param bool  $is_ajax Whether the form is rendered via AJAX.
	 */
	public function enqueue_frontend_assets( $form, $is_ajax ): void {
		unset( $form, $is_ajax );

		wp_enqueue_style(
			'gf-rich-text-block',
			$this->url . 'assets/css/frontend.css',
			array(),
			self::VERSION
		);
	}

	/**
	 * Register the Rich Text Block field with Gravity Forms.
	 */
	public function register_field(): void {
		if ( ! class_exists( 'GF_Fields' ) ) {
			return;
		}

		require_once $this->path . 'includes/class-content-renderer.php';
		require_once $this->path . 'includes/class-content-sanitizer.php';
		require_once $this->path . 'includes/class-field.php';

		\GF_Fields::register( new Field() );
	}
}
