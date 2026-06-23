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
