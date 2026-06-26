<?php
/**
 * Plugin Name:       Rich Text Block for Gravity Forms
 * Description:       Adds a Rich Text Block display field to Gravity Forms for showing formatted content within a form.
 * Version:           0.2.2
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gf-rich-text
 *
 * @package GF_Rich_Text_Block
 */

namespace GF_Rich_Text_Block;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GF_RICH_TEXT_BLOCK_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-plugin.php';

/*
 * Register hooks at plugin-load time rather than from a plugins_loaded callback.
 * Gravity Forms fires its `gform_loaded` action from its own plugins_loaded
 * callback, which can run before ours depending on plugin activation order
 * (the order in the active_plugins option, not alphabetical). If GF loads
 * first, `gform_loaded` would fire before a listener added from our own
 * plugins_loaded callback exists, and the field would never register. Attaching
 * here, at include time, guarantees the listener is in place first; init() also
 * registers immediately if `gform_loaded` has somehow already fired.
 */
( new Plugin( GF_RICH_TEXT_BLOCK_FILE ) )->init();

/**
 * Notify admins when Gravity Forms is not active. Checked at render time so it
 * does not depend on plugin load order.
 */
add_action(
	'admin_notices',
	function () {
		if ( class_exists( 'GFForms' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__(
			'Rich Text Block for Gravity Forms requires Gravity Forms to be installed and active.',
			'gf-rich-text'
		);
		echo '</p></div>';
	}
);
