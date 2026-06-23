<?php
/**
 * Plugin Name:       Rich Text Block for Gravity Forms
 * Description:       Adds a Rich Text Block display field to Gravity Forms for showing formatted content within a form.
 * Version:           1.0.0
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

/**
 * Boot the plugin once all plugins are loaded, guarding on Gravity Forms.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'GFForms' ) ) {
			add_action(
				'admin_notices',
				function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
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
			return;
		}

		( new Plugin( GF_RICH_TEXT_BLOCK_FILE ) )->init();
	}
);
