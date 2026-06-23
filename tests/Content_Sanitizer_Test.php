<?php
/**
 * Tests for Content_Sanitizer.
 *
 * @package GF_Rich_Text_Block
 */

namespace GF_Rich_Text_Block\Tests;

use GF_Rich_Text_Block\Content_Sanitizer;
use PHPUnit\Framework\TestCase;

class Content_Sanitizer_Test extends TestCase {

    public function test_returns_raw_when_user_can_use_unfiltered_html() {
        $html = '<script>evil()</script><p>hi</p>';
        $this->assertSame( $html, Content_Sanitizer::sanitize( $html, true ) );
    }

    public function test_runs_kses_when_user_cannot() {
        $this->assertSame( 'KSES::<p>hi</p>', Content_Sanitizer::sanitize( '<p>hi</p>', false ) );
    }
}
