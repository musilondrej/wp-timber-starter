---
name: add-template
description: Add or change the Twig template for a specific page, post type or archive in this Timber theme. Use when asked to give a page/post its own layout, create a page template selectable in the editor, or change which template renders a URL.
---

# Add a template

## 1. Find what renders today

If the `wordpress` MCP server is connected, call `wp-timber-starter/template-map` with the URL. It returns the post, the template candidates in lookup order and which one renders. Otherwise read the PHP entry file (`page.php`, `single.php`, `index.php`) to see the lookup.

## 2. Pick the lightest option

| Need | Do |
| --- | --- |
| One page (by slug) looks different | Create `views/templates/page-{slug}.twig`; `page.php` tries it first. No PHP. |
| One post type looks different | Create `views/templates/single-{post_type}.twig`. No PHP. |
| One specific post | `views/templates/single-{post_type}-{slug}.twig`. |
| Editors pick a layout per page | A page template (below). |
| Different data, not just markup | Add it to the context in the PHP entry file, or a `Timber\Post` subclass (see `add-post-type`). |

## 3. Page template selectable in the editor

```php
<?php
/**
 * Template Name: Landing
 */

use Timber\Timber;

defined('ABSPATH') || exit;

$context = Timber::context();

Timber::render('templates/landing.twig', $context);
```

Save as `page-templates/landing.php` (WordPress finds page templates one folder deep) and the view as `views/templates/landing.twig`. Don't use a root `templates/` folder: block themes keep their HTML templates there.

## 4. Write the Twig

```twig
{% extends 'layouts/base.twig' %}

{% block content %}
    <article class="{{ post.class }}">
        <h1>{{ post.title|wp_kses_post }}</h1>
        {{ post.content|raw }}
    </article>
{% endblock %}
```

Follow the escaping table in `AGENTS.md`. Strings: `{{ __('Text', 'wp-timber-starter') }}`.

## 5. Verify

- `composer format`, then `composer lint`.
- Call `wp-timber-starter/template-map` again: `renders` should be your new file.
