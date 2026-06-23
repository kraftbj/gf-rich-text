=== Rich Text Block for Gravity Forms ===
Contributors:
Tags: gravity forms, rich text, wysiwyg, content, display field
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.1.5
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

= 0.1.5 =
* Hardened rapid field-switching in the form editor (prevents TinyMCE listener accumulation) and locked the safe merge-tag rendering arguments with a test.

= 0.1.4 =
* Added: a toolbox icon for the field.
* Removed: the Add Media button and the Code (Text) tab; the editor is now Visual-only.
* Removed: the Description setting (the field itself is the rich content).
* Fixed: paragraph breaks now render with proper spacing regardless of theme, and merge-tag replacement no longer injects stray line breaks (nl2br disabled).
* Security/robustness: shortcodes (when opted in) now expand before merge tags, and merge-tag values are HTML-escaped on output.
* Hardened the form-editor sync: content is flushed before switching fields, async editor init and rapid field-switching no longer cross-contaminate or drop content, and dead Text-tab code was removed.

= 0.1.3 =
* Fixed: you could not type in the Rich Content editor on many sites. TinyMCE built inside Gravity Forms' hidden settings panel reported ready but its iframe could not take keyboard focus. The editor is now fully recreated when the field is selected, restoring focus and typing. Verified on a live site.

= 0.1.2 =
* Fixed: the Rich Content editor was not editable because TinyMCE did not initialize inside the (hidden) field settings panel. It now initializes when the field is selected.

= 0.1.1 =
* Fixed: the field did not register when Gravity Forms loaded before this plugin (depending on activation order), so the "Rich Text Block" button was missing from the form editor. Hooks are now attached at plugin-load time.

= 0.1.0 =
* Initial release.
