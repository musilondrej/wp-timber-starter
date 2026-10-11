---
name: add-acf-fields
description: Add ACF custom fields to posts, pages, post types or the theme options page, and output them in Twig. Use when content needs extra structured fields (text, images, repeaters, relationships) managed by editors.
---

# Add ACF fields

Field groups live as JSON in `acf-json/` (`Theme` sets the path). The JSON is the source of truth and is committed.

## 1. Inspect what exists

Call `wp-timber-starter/content-model` (MCP): `acf_field_groups` lists every group with field names and types. Reuse a group if the fields belong together.

## 2. Create or change the group

**Preferred:** the user edits it in wp-admin → ACF → Field Groups on a local or staging site (the ACF menu is hidden on production); ACF writes `acf-json/group_*.json` automatically. Ask them to do that when you can't.

**By hand** (when you must): add/modify `acf-json/group_{slug}.json`.

- Every field needs a unique `key` (`field_` + unique suffix) and the group a unique `key` (`group_…`). Never reuse or change existing keys: stored values are linked by key.
- Bump the group's `modified` (Unix timestamp) so wp-admin offers **Sync**.
- `name` is what templates use: `snake_case`, stable once content exists.

## 3. Output in Twig

| Field | Twig |
| --- | --- |
| Text / textarea | `{{ post.meta('subtitle') }}` |
| WYSIWYG | `{{ post.meta('body')\|wp_kses_post }}` |
| URL | `<a href="{{ post.meta('link') }}">` |
| Image (return format: ID) | `{{ function('wp_get_attachment_image', post.meta('photo'), 'large')\|raw }}` |
| Repeater | `{% for row in post.meta('items') %}{{ row.title }}{% endfor %}` |
| Options page | `{{ options.phone }}` (read lazily, one field at a time, by `src/Options.php`) |

Set image fields to return the **ID**: `wp_get_attachment_image()` then adds `srcset`, `sizes`, `alt` and lazy loading.

## 4. Verify

- `composer lint`.
- Call `wp-timber-starter/content-model` again to see the new fields.
