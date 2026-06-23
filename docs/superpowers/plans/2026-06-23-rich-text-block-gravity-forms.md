# Rich Text Block for Gravity Forms — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a standalone WordPress plugin that registers a new Gravity Forms display field, "Rich Text Block," letting form creators author formatted content in a TinyMCE editor that renders (with merge tags resolved) on the front end.

**Architecture:** A `GF_Field` subclass registers the `rich_text_block` field type. Two pure-logic classes — `Content_Sanitizer` (save-time `wp_kses_post` gating on `unfiltered_html`) and `Content_Renderer` (merge-tag replacement + optional shortcodes) — hold all testable logic so they can be unit-tested with GF/WP stubbed. A `Plugin` bootstrap wires registration, the TinyMCE field setting, asset enqueues, and the admin sync JS. Content lives in the field's existing `content` property (no new storage).

**Tech Stack:** PHP 8.0+, WordPress 6.4+, Gravity Forms 2.7+, TinyMCE via `wp_editor()`, Composer, PHPCS (Jetpack standard), PHPUnit 9.

## Global Constraints

- PHP: `>=8.0` (`Requires PHP: 8.0`). WordPress: `Requires at least: 6.4`. Gravity Forms: 2.7+.
- Text domain: `gf-rich-text` (slug, also used in all i18n calls).
- PHP namespace: `GF_Rich_Text_Block\`. Global prefixes: `gf_rich_text_block` / `GF_Rich_Text_Block`.
- Field type id: `rich_text_block`. Display name: "Rich Text Block".
- Display-only: `displayOnly = true`; never saved to entries, notifications, or `{all_fields}`.
- PHPCS must pass the **Jetpack** standard (`automattic/jetpack-codesniffer`).
- License: `GPL-2.0-or-later`.
- Commit messages use conventional-commit prefixes (`feat:`, `test:`, `chore:`, `docs:`, etc.) — the repo enforces this via a hook.

## File Structure

```
gf-rich-text.php                          plugin header, GF dependency guard, bootstrap
includes/class-plugin.php                 hooks, asset enqueue, field-setting markup
includes/class-field.php                  GF_Field subclass (rich_text_block)
includes/class-content-renderer.php       pure: merge-tag + shortcode processing
includes/class-content-sanitizer.php      pure: unfiltered_html-gated wp_kses_post
assets/js/admin-editor.js                 TinyMCE <-> GF field-property sync + preview + merge tags
assets/css/admin-editor.css               form-builder editor styling
assets/css/frontend.css                   front-end content wrapper styling
tests/bootstrap.php                       WP/GF stubs for unit tests
tests/test-content-sanitizer.php          unit tests (pure logic)
tests/test-content-renderer.php           unit tests (pure logic)
composer.json, phpcs.xml.dist, phpunit.xml.dist
readme.txt, README.md
docs/QA-CHECKLIST.md                      manual QA checklist (GF-integration parts)
```

---

### Task 1: Project scaffolding, tooling, and Gravity Forms dependency guard

**Files:**
- Create: `composer.json`, `phpcs.xml.dist`, `phpunit.xml.dist`, `gf-rich-text.php`, `includes/class-plugin.php`
- Modify: `.gitignore`

**Interfaces:**
- Produces: constant `GF_RICH_TEXT_BLOCK_FILE`; class `GF_Rich_Text_Block\Plugin` with `__construct( string $file )` and `init(): void` (body filled in Task 4); a `plugins_loaded` bootstrap that shows an admin notice and no-ops when `GFForms` is absent.

- [ ] **Step 1: Create `composer.json`**

```json
{
    "name": "gf-rich-text/gf-rich-text",
    "description": "Adds a Rich Text Block display field to Gravity Forms for showing formatted content within a form.",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=8.0"
    },
    "require-dev": {
        "automattic/jetpack-codesniffer": "^8.0",
        "dealerdirect/phpcodesniffer-composer-installer": "^1.0",
        "phpunit/phpunit": "^12.0"
    },
    "scripts": {
        "lint": "phpcs",
        "lint:fix": "phpcbf",
        "test": "phpunit"
    },
    "config": {
        "allow-plugins": {
            "dealerdirect/phpcodesniffer-composer-installer": true
        }
    }
}
```

- [ ] **Step 2: Install dependencies**

Run: `composer install`
Expected: `vendor/` created; `vendor/bin/phpcs` and `vendor/bin/phpunit` exist; the Jetpack standard is registered (verify next step).

- [ ] **Step 3: Verify the Jetpack standard is installed**

Run: `vendor/bin/phpcs -i`
Expected: output includes `Jetpack` in the list of installed coding standards.

- [ ] **Step 4: Create `phpcs.xml.dist`**

```xml
<?xml version="1.0"?>
<ruleset name="GF Rich Text Block">
    <description>Coding standards for Rich Text Block for Gravity Forms.</description>

    <file>.</file>

    <exclude-pattern>/vendor/*</exclude-pattern>
    <exclude-pattern>/node_modules/*</exclude-pattern>
    <exclude-pattern>/tests/*</exclude-pattern>

    <arg name="extensions" value="php"/>
    <arg value="ps"/>
    <arg name="parallel" value="8"/>

    <rule ref="Jetpack"/>

    <config name="testVersion" value="8.0-"/>
    <config name="minimum_supported_wp_version" value="6.4"/>

    <rule ref="WordPress.WP.I18n">
        <properties>
            <property name="text_domain" type="array">
                <element value="gf-rich-text"/>
            </property>
        </properties>
    </rule>

    <rule ref="WordPress.NamingConventions.PrefixAllGlobals">
        <properties>
            <property name="prefixes" type="array">
                <element value="gf_rich_text_block"/>
                <element value="GF_Rich_Text_Block"/>
            </property>
        </properties>
    </rule>
</ruleset>
```

- [ ] **Step 5: Create `phpunit.xml.dist`**

```xml
<?xml version="1.0"?>
<phpunit bootstrap="tests/bootstrap.php" colors="true" cacheResult="false">
    <testsuites>
        <testsuite name="unit">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 6: Create the bootstrap class `includes/class-plugin.php`**

The `init()` body is intentionally empty here; Task 4 fills it. This task only needs the class to exist and construct.

```php
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
```

- [ ] **Step 7: Create the main plugin file `gf-rich-text.php`**

```php
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
```

- [ ] **Step 8: Update `.gitignore`**

```
/vendor/
/node_modules/
.DS_Store
*.log
```

- [ ] **Step 9: Run PHPCS on the PHP written so far**

Run: `vendor/bin/phpcs`
Expected: PASS (no errors). Fix any reported issues with `vendor/bin/phpcbf` and re-run.

- [ ] **Step 10: Commit**

```bash
git add composer.json composer.lock phpcs.xml.dist phpunit.xml.dist gf-rich-text.php includes/class-plugin.php .gitignore
git commit -m "chore: scaffold plugin, tooling, and GF dependency guard"
```

---

### Task 2: Content_Sanitizer (pure logic, TDD)

**Files:**
- Create: `includes/class-content-sanitizer.php`, `tests/bootstrap.php`, `tests/test-content-sanitizer.php`

**Interfaces:**
- Produces: `GF_Rich_Text_Block\Content_Sanitizer::sanitize( string $content, bool $can_use_unfiltered_html ): string` — returns `$content` unchanged when the bool is true, otherwise returns `wp_kses_post( $content )`.

- [ ] **Step 1: Create the test stubs `tests/bootstrap.php`**

```php
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
require_once __DIR__ . '/../includes/class-content-renderer.php';
```

> Note: `class-content-renderer.php` is required here but created in Task 3. If running Task 2 in isolation before Task 3, temporarily comment that last `require_once`; Task 3 uncomments it. (Subagent-driven execution runs tasks in order, so it will exist.)

- [ ] **Step 2: Write the failing test `tests/test-content-sanitizer.php`**

```php
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
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter Content_Sanitizer_Test`
Expected: FAIL — `Error: Class "GF_Rich_Text_Block\Content_Sanitizer" not found`.

- [ ] **Step 4: Implement `includes/class-content-sanitizer.php`**

No ABSPATH guard is needed: the class only defines a method and calls `wp_kses_post()` lazily, so it loads cleanly under both WordPress and PHPUnit.

```php
<?php
/**
 * Save-time content sanitization.
 *
 * @package GF_Rich_Text_Block
 */

namespace GF_Rich_Text_Block;

/**
 * Sanitizes authored rich-text content based on the author's capability.
 */
class Content_Sanitizer {

    /**
     * Sanitize content for storage.
     *
     * @param string $content                 Raw authored HTML.
     * @param bool   $can_use_unfiltered_html Whether the author may store unfiltered HTML.
     * @return string Sanitized content.
     */
    public static function sanitize( string $content, bool $can_use_unfiltered_html ): string {
        if ( $can_use_unfiltered_html ) {
            return $content;
        }

        return wp_kses_post( $content );
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter Content_Sanitizer_Test`
Expected: PASS (2 tests, 2 assertions).

- [ ] **Step 6: Commit**

```bash
git add includes/class-content-sanitizer.php tests/bootstrap.php tests/test-content-sanitizer.php
git commit -m "feat: add capability-gated content sanitizer"
```

---

### Task 3: Content_Renderer (pure logic, TDD)

**Files:**
- Create: `includes/class-content-renderer.php`, `tests/test-content-renderer.php`

**Interfaces:**
- Consumes: stubbed `GFCommon::replace_variables()` and `do_shortcode()` from `tests/bootstrap.php`.
- Produces: `GF_Rich_Text_Block\Content_Renderer::render( string $content, array $form, $entry, bool $run_shortcodes ): string` — runs `GFCommon::replace_variables( $content, $form, $entry )`, then `do_shortcode()` only when `$run_shortcodes` is true. Returns the processed inner HTML (no wrapping element — the field adds the wrapper).

- [ ] **Step 1: Write the failing test `tests/test-content-renderer.php`**

```php
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
        $out = Content_Renderer::render( '[gallery]', array( 'id' => 1 ), null, true );
        $this->assertSame( 'SC::REPLACED::[gallery]', $out );
    }

    public function test_skips_shortcodes_when_disabled() {
        $out = Content_Renderer::render( '[gallery]', array( 'id' => 1 ), null, false );
        $this->assertSame( 'REPLACED::[gallery]', $out );
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter Content_Renderer_Test`
Expected: FAIL — `Class "GF_Rich_Text_Block\Content_Renderer" not found`.

- [ ] **Step 3: Implement `includes/class-content-renderer.php`**

```php
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
        $content = \GFCommon::replace_variables( $content, $form, $entry );

        if ( $run_shortcodes ) {
            $content = do_shortcode( $content );
        }

        return $content;
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter Content_Renderer_Test`
Expected: PASS (3 tests, 3 assertions).

- [ ] **Step 5: Run the full unit suite**

Run: `vendor/bin/phpunit`
Expected: PASS (5 tests total across both files).

- [ ] **Step 6: Commit**

```bash
git add includes/class-content-renderer.php tests/test-content-renderer.php
git commit -m "feat: add merge-tag and shortcode content renderer"
```

---

### Task 4: Field registration and front-end rendering

**Files:**
- Create: `includes/class-field.php`
- Modify: `includes/class-plugin.php` (fill `init()`, add `register_field()`)

**Interfaces:**
- Consumes: `Content_Renderer::render()` (Task 3); `Content_Sanitizer` is referenced for require only here, used in Task 6.
- Produces: class `GF_Rich_Text_Block\Field extends \GF_Field` with `public $type = 'rich_text_block'`, `public $displayOnly = true`, and `get_field_input( $form, $value, $entry ): string`. `Plugin::register_field()` requires the include files and calls `\GF_Fields::register( new Field() )` on `gform_loaded`.

- [ ] **Step 1: Create `includes/class-field.php`**

```php
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
        return 'gform-icon--paragraph';
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
            'description_setting',
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
}
```

> The `content` property is inherited from `GF_Field` (GF maps unknown field properties onto the object), so no declaration is needed; we read it defensively with `isset()`.

- [ ] **Step 2: Fill `Plugin::init()` and add `register_field()` in `includes/class-plugin.php`**

Replace the empty `init()` method body with:

```php
    /**
     * Register hooks.
     */
    public function init(): void {
        add_action( 'gform_loaded', array( $this, 'register_field' ), 5 );
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
```

- [ ] **Step 3: Run PHPCS**

Run: `vendor/bin/phpcs`
Expected: PASS. Fix with `vendor/bin/phpcbf` if needed and re-run.

- [ ] **Step 4: Manual QA — field registration and front-end render**

Prerequisite: a WordPress site with Gravity Forms 2.7+ active and this plugin active (local install or WP Playground with a GF zip mounted).

Verify:
1. In **Forms → New Form → add field**, a "Rich Text Block" button appears in the **Standard Fields** group.
2. Add it to a form; it appears on the canvas with the placeholder preview text.
3. Temporarily set the field's content by editing form meta (or wait for Task 5), then preview the form: a `<div class="gf-rich-text-block">` renders the content on the front end.
4. The field does **not** appear in the entry list / entry detail after a submission.

Record results in `docs/QA-CHECKLIST.md` (created in Task 7); for now just confirm the field registers and renders.

- [ ] **Step 5: Commit**

```bash
git add includes/class-field.php includes/class-plugin.php
git commit -m "feat: register rich_text_block field and render content on front end"
```

---

### Task 5: Authoring UI — TinyMCE field setting, JS sync, merge tags, live preview

**Files:**
- Modify: `includes/class-plugin.php` (add setting markup + enqueues to `init()`)
- Create: `assets/js/admin-editor.js`, `assets/css/admin-editor.css`

**Interfaces:**
- Consumes: GF JS globals `GetSelectedField()`, `SetFieldProperty()`, the jQuery event `gform_load_field_settings`, and `window.form`.
- Produces: a TinyMCE editor (textarea id `gf_rich_text_block_editor`) inside the `rich_content_setting` settings row; JS that loads `field.content` on selection, writes edits back via `SetFieldProperty('content', html)`, updates the canvas preview live, and inserts merge tags at the cursor.

- [ ] **Step 1: Add hooks to `Plugin::init()`**

Add these lines to the `init()` body (after the `gform_loaded` line):

```php
        add_action( 'gform_field_standard_settings', array( $this, 'render_rich_content_setting' ), 25, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'gform_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ), 10, 2 );
```

- [ ] **Step 2: Add the setting markup + enqueue methods to `Plugin`**

```php
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
```

- [ ] **Step 3: Create `assets/js/admin-editor.js`**

```js
/* global jQuery, tinymce, GetSelectedField, SetFieldProperty, form */
( function( $ ) {
	'use strict';

	var EDITOR_ID = 'gf_rich_text_block_editor';
	var bound = false;

	function getEditor() {
		return ( window.tinymce && tinymce.get( EDITOR_ID ) ) || null;
	}

	function isOurField( field ) {
		return field && field.type === 'rich_text_block';
	}

	function loadContent( field ) {
		var content = field && field.content ? field.content : '';
		var editor = getEditor();
		if ( editor ) {
			editor.setContent( content );
		} else {
			$( '#' + EDITOR_ID ).val( content );
		}
	}

	function updatePreview( html ) {
		if ( ! window.GetSelectedField ) {
			return;
		}
		var field = GetSelectedField();
		if ( ! isOurField( field ) ) {
			return;
		}
		$( '#field_' + field.id + ' .gf-rich-text-block--preview' ).html( html || '' );
	}

	function persist() {
		if ( ! window.GetSelectedField ) {
			return;
		}
		var field = GetSelectedField();
		if ( ! isOurField( field ) ) {
			return;
		}
		var editor = getEditor();
		var html = editor ? editor.getContent() : $( '#' + EDITOR_ID ).val();
		SetFieldProperty( 'content', html );
		updatePreview( html );
	}

	function bindEditorEvents() {
		var editor = getEditor();
		if ( ! editor || bound ) {
			return;
		}
		bound = true;
		editor.on( 'change keyup SetContent ExecCommand', persist );
	}

	function populateMergeTags() {
		var $select = $( '#gf_rtb_mergetag_select' );
		if ( ! $select.length || typeof form === 'undefined' || ! form.fields ) {
			return;
		}
		// Keep the placeholder option; rebuild the rest from the current form.
		$select.find( 'option:gt(0)' ).remove();
		form.fields.forEach( function( f ) {
			if ( f.type === 'rich_text_block' || ! f.label ) {
				return;
			}
			$select.append(
				$( '<option></option>' )
					.val( '{' + f.label + ':' + f.id + '}' )
					.text( f.label )
			);
		} );
	}

	function insertMergeTag( tag ) {
		if ( ! tag ) {
			return;
		}
		var editor = getEditor();
		if ( editor ) {
			editor.insertContent( tag );
		} else {
			var $ta = $( '#' + EDITOR_ID );
			$ta.val( ( $ta.val() || '' ) + tag );
		}
		persist();
	}

	$( document ).on( 'gform_load_field_settings', function( event, field ) {
		if ( ! isOurField( field ) ) {
			return;
		}
		loadContent( field );
		bindEditorEvents();
		populateMergeTags();
	} );

	$( document ).on( 'change', '#gf_rtb_mergetag_select', function() {
		insertMergeTag( $( this ).val() );
		$( this ).val( '' );
	} );
} )( jQuery );
```

- [ ] **Step 4: Create `assets/css/admin-editor.css`**

```css
.gf-rich-text-block--preview {
	padding: 8px 12px;
	border: 1px dashed #c3c4c7;
	border-radius: 4px;
	background: #fff;
}

.gf-rtb-mergetags {
	margin-top: 8px;
}

.gf-rtb-mergetag-select {
	max-width: 100%;
}
```

- [ ] **Step 5: Run PHPCS**

Run: `vendor/bin/phpcs`
Expected: PASS. (JS/CSS are not linted by this ruleset; PHP must be clean.)

- [ ] **Step 6: Manual QA — authoring round-trip**

On the GF-active test site:
1. Add a Rich Text Block field; the **Rich Content** TinyMCE editor appears in field settings with the curated toolbar and **Add Media** button.
2. Type formatted content (bold, a bulleted list, a link). The canvas preview updates live.
3. Insert a merge tag via the dropdown; it appears at the cursor in the editor.
4. Insert an image from the media library.
5. Toggle Visual ↔ Text tabs; raw HTML is editable.
6. **Save the form, reload the editor.** Select the field — the editor and preview repopulate from saved `content` (verifies `SetFieldProperty` persisted and `gform_load_field_settings` reload works).
7. Switch to another field and back; the editor swaps content correctly (verifies the shared-panel sync).
8. Front end: merge tag resolves to the referenced field's value after submission flow; in the builder it shows literally.

Note any failures; the shared-panel TinyMCE sync (steps 6–7) is the highest-risk area.

- [ ] **Step 7: Commit**

```bash
git add includes/class-plugin.php assets/js/admin-editor.js assets/css/admin-editor.css
git commit -m "feat: add TinyMCE authoring UI with live preview and merge tags"
```

---

### Task 6: Save-time sanitization

**Files:**
- Modify: `includes/class-field.php` (override `sanitize_settings()`)

**Interfaces:**
- Consumes: `Content_Sanitizer::sanitize()` (Task 2); `current_user_can( 'unfiltered_html' )`.
- Produces: `Field::sanitize_settings(): void` that sanitizes `$this->content` on form save.

- [ ] **Step 1: Add `sanitize_settings()` to `Field`**

Add this method to the `Field` class (after `get_field_input()`):

```php
    /**
     * Sanitize field settings when the form is saved.
     *
     * Mirrors core behavior: authors with the `unfiltered_html` capability keep
     * raw markup; everyone else has content run through wp_kses_post().
     */
    public function sanitize_settings() {
        parent::sanitize_settings();

        $can_unfiltered = function_exists( 'current_user_can' ) && current_user_can( 'unfiltered_html' );

        if ( isset( $this->content ) ) {
            $this->content = Content_Sanitizer::sanitize( (string) $this->content, (bool) $can_unfiltered );
        }
    }
```

- [ ] **Step 2: Run PHPCS**

Run: `vendor/bin/phpcs`
Expected: PASS.

- [ ] **Step 3: Run the unit suite (regression check)**

Run: `vendor/bin/phpunit`
Expected: PASS (still 5 tests; sanitizer logic unchanged).

- [ ] **Step 4: Manual QA — sanitization**

1. As an administrator (has `unfiltered_html` on single-site): save a Rich Text Block containing `<script>` — it is preserved.
2. As an Editor on a multisite (or a role lacking `unfiltered_html`): save content containing `<script>alert(1)</script><p>ok</p>` — on reload the `<script>` is stripped, `<p>ok</p>` remains.

- [ ] **Step 5: Commit**

```bash
git add includes/class-field.php
git commit -m "feat: sanitize content on save based on unfiltered_html capability"
```

---

### Task 7: Front-end styling, distribution docs, and QA checklist

**Files:**
- Create: `assets/css/frontend.css`, `readme.txt`, `README.md`, `docs/QA-CHECKLIST.md`

**Interfaces:**
- Produces: front-end stylesheet (enqueued in Task 5), WordPress.org `readme.txt`, developer `README.md`, and a manual QA checklist documenting the GF-integration test steps from Tasks 4–6.

- [ ] **Step 1: Create `assets/css/frontend.css`**

```css
.gf-rich-text-block {
	margin: 0 0 1em;
}

.gf-rich-text-block :first-child {
	margin-top: 0;
}

.gf-rich-text-block :last-child {
	margin-bottom: 0;
}

.gf-rich-text-block img {
	max-width: 100%;
	height: auto;
}
```

- [ ] **Step 2: Create `readme.txt`**

```
=== Rich Text Block for Gravity Forms ===
Contributors: 
Tags: gravity forms, rich text, wysiwyg, content, display field
Requires at least: 6.4
Tested up to: 6.5
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add a Rich Text Block display field to Gravity Forms for showing formatted content within a form.

== Description ==

Rich Text Block adds a new display field to Gravity Forms. Author formatted
content (headings, lists, links, images) in a TinyMCE editor right in the form
builder. Content renders on the front end with Gravity Forms merge tags resolved.

The field is display-only: it never collects input and is never saved to entries.

Features:

* Dedicated "Rich Text Block" field in the Standard Fields group.
* TinyMCE editor with media library, Visual/Text tabs, and a curated toolbar.
* Gravity Forms merge-tag insertion and resolution on output.
* Live preview in the form builder.
* Conditional logic (visibility) and Custom CSS Class support.
* Capability-aware sanitization (wp_kses_post for users without unfiltered_html).

== Installation ==

1. Upload the plugin to /wp-content/plugins/ and activate it.
2. Requires Gravity Forms 2.7 or later to be installed and active.
3. Add a "Rich Text Block" field to any form from the Standard Fields group.

== Changelog ==

= 1.0.0 =
* Initial release.
```

- [ ] **Step 3: Create `README.md`**

```markdown
# Rich Text Block for Gravity Forms

A standalone WordPress plugin that registers a new Gravity Forms **display field**,
"Rich Text Block." Form creators author formatted content in a TinyMCE editor in the
form builder; it renders on the front end with merge tags resolved. Display-only —
never collects input, never saved to entries.

## Requirements

- PHP 8.0+
- WordPress 6.4+
- Gravity Forms 2.7+

## Development

```bash
composer install      # install dev tooling
composer lint         # PHPCS (Jetpack standard)
composer lint:fix     # PHPCBF autofix
composer test         # PHPUnit (pure-logic unit tests)
```

## Architecture

- `includes/class-field.php` — the `rich_text_block` `GF_Field` subclass.
- `includes/class-content-renderer.php` — merge-tag + optional shortcode processing (unit-tested).
- `includes/class-content-sanitizer.php` — capability-gated `wp_kses_post` (unit-tested).
- `includes/class-plugin.php` — registration, TinyMCE setting, asset enqueues.
- `assets/js/admin-editor.js` — TinyMCE ↔ GF field-property sync, live preview, merge tags.

## Filters

- `gf_rich_text_block_run_shortcodes` (bool, default `false`) — opt in to shortcode expansion in rendered content.

## Manual QA

See `docs/QA-CHECKLIST.md`. Full end-to-end testing requires a licensed Gravity Forms install.
```

- [ ] **Step 4: Create `docs/QA-CHECKLIST.md`**

```markdown
# Manual QA Checklist — Rich Text Block for Gravity Forms

Prerequisite: WordPress 6.4+ with Gravity Forms 2.7+ and this plugin active.

## Registration & editor
- [ ] "Rich Text Block" button appears in the **Standard Fields** group.
- [ ] Adding the field shows the placeholder preview on the canvas.
- [ ] Field settings show the **Rich Content** TinyMCE editor with the curated toolbar and Add Media button.

## Authoring
- [ ] Bold, italic, bullet/numbered lists, links, headings, alignment all apply.
- [ ] Add Media inserts an image; image renders constrained to container width on the front end.
- [ ] Visual ↔ Text tab toggle works; raw HTML editable.
- [ ] Merge-tag dropdown inserts a tag at the cursor.
- [ ] Canvas preview updates live while typing.

## Persistence (highest-risk: shared settings panel)
- [ ] Save form, reload editor, reselect field — editor + preview repopulate from saved content.
- [ ] Switching between this field and another swaps editor content correctly.

## Behaviors
- [ ] Conditional logic show/hide works on the front end.
- [ ] Custom CSS Class is applied to the wrapper.
- [ ] Renders correctly on each page of a multi-page form.
- [ ] Field is absent from entry list, entry detail, notifications, and {all_fields}.

## Rendering
- [ ] Front end shows formatted content inside `<div class="gf-rich-text-block">`.
- [ ] Merge tags resolve on the front end; show literally in the builder preview.

## Security
- [ ] User WITH unfiltered_html: `<script>` preserved on save.
- [ ] User WITHOUT unfiltered_html: `<script>` stripped, other markup kept.
- [ ] Shortcodes NOT expanded by default; expanded when `gf_rich_text_block_run_shortcodes` returns true.
```

- [ ] **Step 5: Run PHPCS and the unit suite (final gate)**

Run: `vendor/bin/phpcs && vendor/bin/phpunit`
Expected: both PASS.

- [ ] **Step 6: Commit**

```bash
git add assets/css/frontend.css readme.txt README.md docs/QA-CHECKLIST.md
git commit -m "docs: add front-end styles, readme, and manual QA checklist"
```

---

## Self-Review Notes (plan author)

**Spec coverage check:**
- New dedicated field type → Tasks 4, 5. ✅
- TinyMCE editor (media, Visual/Text, curated toolbar, live preview) → Task 5. ✅
- Merge-tag support (insertion + resolution) → Task 5 (insert), Task 3/4 (resolve). ✅
- Conditional logic, CSS class, multi-page → Task 4 settings + QA in Task 7. ✅
- Display-only / not in entries → `displayOnly = true`, Task 4; QA Task 7. ✅
- Capability-gated sanitization → Tasks 2 + 6. ✅
- Shortcodes off by default behind a filter → Task 3 (param) + Task 4 (filter). ✅
- Tooling: git, composer, Jetpack PHPCS, PHPUnit → Task 1. ✅
- Merge-tags-literal-in-preview behavior → Task 4 (`is_form_editor` branch) + QA. ✅
- Naming/versions/license/text-domain → Global Constraints + Task 1 header. ✅
- Out-of-scope items (Gutenberg, input collection, entries, settings page) → not built. ✅

**Known risks carried into execution:**
- The exact PHPCS sniff names/property names in the Jetpack ruleset may need minor adjustment after `composer install` (Task 1 Step 3 verifies the standard name). If `minimum_supported_wp_version` is rejected, check the installed WPCS version's config key.
- The TinyMCE-in-shared-GF-panel sync (Task 5) is integration-tested manually, not via unit tests — it's the part most likely to need iteration.
