=== Rich Text Block for Gravity Forms ===
Contributors:
Tags: gravity forms, rich text, wysiwyg, content, display field
Requires at least: 6.4
Tested up to: 7.0
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
