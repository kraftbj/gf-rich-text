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
