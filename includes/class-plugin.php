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
	 * Register hooks. Filled in Task 4.
	 */
	public function init(): void {
	}
}
