<?php
/**
 * Tests for Content_Renderer.
 *
 * @package GF_Rich_Text_Block
 */

namespace GF_Rich_Text_Block\Tests;

use GF_Rich_Text_Block\Content_Renderer;
use PHPUnit\Framework\TestCase;

class Content_Renderer_Test extends TestCase {

	public function test_replaces_merge_tags() {
		$out = Content_Renderer::render( 'Hello {Name:1}', array( 'id' => 1 ), null, false );
		$this->assertSame( 'REPLACED::Hello {Name:1}', $out );
	}

	public function test_runs_shortcodes_when_enabled() {
		// Shortcodes expand first (SC::), then merge tags replace (REPLACED::),
		// so submitter values cannot inject shortcode syntax that runs afterward.
		$out = Content_Renderer::render( '[gallery]', array( 'id' => 1 ), null, true );
		$this->assertSame( 'REPLACED::SC::[gallery]', $out );
	}

	public function test_skips_shortcodes_when_disabled() {
		$out = Content_Renderer::render( '[gallery]', array( 'id' => 1 ), null, false );
		$this->assertSame( 'REPLACED::[gallery]', $out );
	}

	public function test_replace_variables_called_with_safe_arguments() {
		// Locks the security/formatting contract: url_encode=false, esc_html=true
		// (escape submitter values), nl2br=false (no spurious <br>), format=html.
		Content_Renderer::render( 'x', array( 'id' => 1 ), null, false );
		$this->assertSame( array( false, true, false, 'html' ), \GFCommon::$last_replace_args );
	}

	public function test_untargeted_link_opens_in_new_tab_when_forced() {
		$out = Content_Renderer::process_links( '<a href="https://example.com">x</a>', true );
		$this->assertStringContainsString( 'target="_blank"', $out );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $out );
	}

	public function test_untargeted_link_left_alone_when_not_forced() {
		$out = Content_Renderer::process_links( '<a href="https://example.com">x</a>', false );
		$this->assertStringNotContainsString( 'target=', $out );
	}

	public function test_author_chosen_same_tab_is_respected() {
		$out = Content_Renderer::process_links( '<a href="https://example.com" target="_self">x</a>', true );
		$this->assertStringContainsString( 'target="_self"', $out );
		$this->assertStringNotContainsString( '_blank', $out );
	}

	public function test_author_chosen_new_tab_gets_security_rel() {
		$out = Content_Renderer::process_links( '<a target="_blank" href="https://example.com">x</a>', false );
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $out );
	}

	public function test_non_link_content_unchanged() {
		$html = '<p>No links here</p>';
		$this->assertSame( $html, Content_Renderer::process_links( $html, true ) );
	}
}
