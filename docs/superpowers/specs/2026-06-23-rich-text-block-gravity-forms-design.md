# Rich Text Block for Gravity Forms — Design

**Status:** Approved (design phase)
**Date:** 2026-06-23

## 1. Overview

A standalone WordPress plugin that registers a new Gravity Forms **display field**
called "Rich Text Block." Form creators drop it into a form and author formatted
content in a TinyMCE editor directly in the form builder. On the front end the
content renders as HTML, with Gravity Forms merge tags resolved.

It is display-only: like the native HTML field, it never collects input and never
saves to entries. It exists to give non-technical form creators a WYSIWYG way to
place formatted instructional/display content into a form without hand-writing HTML.

### Why this exists

The only existing solution targeting this use case is the Gravity Wiz "Rich Text
HTML Fields" snippet, which hijacks the core HTML field, uses legacy TinyMCE, lacks
a live preview, and leaves merge-tag support unfinished. This plugin ships a proper,
dedicated field type that closes those gaps.

## 2. Locked decisions

- Editor: **TinyMCE** (via `wp_editor()`).
- A **new dedicated field type**, not an enhancement of the HTML field.
- **Full merge-tag support** (content run through `GFCommon::replace_variables()`;
  merge-tag inserter in the editor).
- Standard GF behaviors supported: **conditional logic (visibility)**,
  **Custom CSS Class + field width**, **multi-page aware** (default display-field
  behavior).
- Display-only: **not** stored in entries, notifications, or `{all_fields}`.
- Name: **"Rich Text Block for Gravity Forms"**; slug `gf-rich-text`; field type id
  `rich_text_block`; PHP namespace `GF_Rich_Text_Block\`.
- Targets: **PHP 8.0+, WP 6.4+, GF 2.7+**.
- PHPCS gate using the **Jetpack** standard (`automattic/jetpack-codesniffer`).

## 3. Architecture & components

```
gf-rich-text.php                              plugin header, GF dependency guard, bootstrap
includes/class-plugin.php                     hooks, asset enqueue, sanitize filter wiring
includes/class-field.php                      GF_Field subclass (the field; the heart)
assets/js/admin-editor.js                     TinyMCE <-> GF field-property sync + live preview
assets/css/admin-editor.css                   form-builder editor styling
assets/css/frontend.css                       front-end content wrapper styling
composer.json, phpcs.xml.dist, .gitignore     tooling
readme.txt (WP.org), README.md                docs
```

### The field class — `GF_Rich_Text_Block\Field` (extends `GF_Field`)

- `type = 'rich_text_block'`.
- `get_form_editor_field_title()` -> "Rich Text Block".
- `get_form_editor_field_icon()` -> a dashicon/SVG.
- `get_form_editor_button()` -> places the toolbox button in the **Standard Fields**
  group, next to HTML.
- `get_form_editor_field_settings()` -> exposes:
  - custom **Rich Content** setting (the TinyMCE editor),
  - **Conditional Logic**,
  - **Custom CSS Class** + field width (Appearance),
  - label/label-visibility settings consistent with a display field.
- `get_field_input( $form, $value, $entry )`:
  - **form editor context** -> renders a live preview of the content (merge tags
    shown literally — see §4).
  - **front end** -> returns the content passed through
    `GFCommon::replace_variables( $content, $form, $entry )`, wrapped in
    `<div class="gf-rich-text-block ...customclasses">`.
- `is_conditional_logic_supported()` -> `true`.
- `displayOnly = true` so the field is excluded from entries.
- `sanitize_settings()` overridden for save-time sanitization (see §4).
- Content stored in the field's existing `content` property (same convention the
  core HTML field uses) — no new storage.

### Bootstrap — `GF_Rich_Text_Block\Plugin`

- Registers the field via `GF_Fields::register()` on the appropriate GF init hook.
- Enqueues `admin-editor.js`/`.css` only on the GF form-editor admin screen.
- Enqueues `frontend.css` only when a form renders.
- Wires the shortcode-execution filter (default off, see §4).

## 4. Data flow

### Authoring (form builder)
- `wp_editor()` renders TinyMCE inside the field settings panel with:
  - media button (WP media library image insertion),
  - Visual + Text (raw HTML) tabs,
  - curated toolbar: bold, italic, bullet list, numbered list, link, headings,
    alignment,
  - a Gravity Forms **merge-tag inserter** dropdown.
- `admin-editor.js` listens for GF's `gform_load_field_settings` JS event to load the
  selected field's `content` into the TinyMCE instance, and writes edits back via
  `SetFieldProperty( 'content', ... )` on editor change/blur.
- The **live preview** (rendered by `get_field_input()` in editor context) updates on
  save.

**The hard part:** TinyMCE must live inside GF's single, shared, reused field-settings
panel (the same DOM is repopulated as the user selects different fields). All of that
synchronization complexity is isolated in `admin-editor.js`. This is the main
integration risk and the area needing the most careful manual QA.

### Storage
- Content lives in the form's field config (GF form meta). No new tables, no entry
  columns, no options.

### Rendering (front end)
- `content` is run through `GFCommon::replace_variables( $content, $form, $entry )`.
- Note: on initial render there is no submitted `$entry`, so entry-dependent merge
  tags resolve to empty; field/user/embed-style tags resolve as GF allows. This
  matches standard GF display-field behavior.

### Merge tags in the live preview
- In the form builder there is no submission, so the preview renders merge tags
  **literally** (e.g. shows `{Name:1}`), with a short inline note. Real replacement
  happens only on the front end. This is explicit, expected behavior — not a bug.

## 5. Security

- **On save** (`sanitize_settings()`): mirror core GF/WP behavior — users **with** the
  `unfiltered_html` capability keep raw markup; users **without** it have content run
  through `wp_kses_post()`. Prevents low-privileged authors from injecting scripts.
- **On output:** content is intentionally HTML and is echoed after the save-time
  sanitization. Merge-tag replacement goes through GF's own `replace_variables()`.
- **Shortcodes:** execution is **off by default**, exposed behind a filter
  (e.g. `gf_rich_text_block_run_shortcodes`) for sites that opt in.
- Capability to author the field is governed by GF's existing form-editor
  permissions.

## 6. Testing

- **Automated gate:** PHPCS (Jetpack standard) must be clean.
- **Unit tests** (PHPUnit, GF mocked) for the pure logic:
  - sanitization branch with/without `unfiltered_html`,
  - the merge-tag/shortcode output wrapper (shortcodes off vs. on via filter).
- **Manual QA checklist** (documented) for the GF-integration parts that cannot be
  unit-tested without a licensed GF install — ideally run on a WP Playground or local
  site. Covers: toolbox button placement, authoring + media insert, Visual/Text
  toggle, merge-tag inserter, save/reload round-trip, live preview, conditional-logic
  show/hide, multi-page rendering, front-end output + merge-tag resolution, and the
  sanitization behavior for non-`unfiltered_html` users.

## 7. Tooling & project meta

- `git init` + `.gitignore` (vendor/, node_modules/, .DS_Store, etc.).
- `composer.json` dev deps: `automattic/jetpack-codesniffer` +
  `dealerdirect/phpcodesniffer-composer-installer`; scripts `composer lint` /
  `composer lint:fix`.
- `phpcs.xml.dist`: **Jetpack** standard, configured with text domain `gf-rich-text`,
  `testVersion 8.0-`, `minimum_supported_wp_version 6.4`.
- Plugin header: `Requires PHP: 8.0`, `Requires at least: 6.4`, plus a runtime guard
  that shows an admin notice and no-ops if Gravity Forms is not active.

## 8. Out of scope

- Gutenberg block editor inside the form builder.
- Rich-text **input** collection (submitter-entered formatted text).
- Storing content into entries, notifications, or `{all_fields}`.
- A settings/admin page beyond the field itself.
