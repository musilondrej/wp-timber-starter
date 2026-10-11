---
name: add-post-type
description: Register a custom post type (and optionally a taxonomy) in this Timber theme, with its own Timber\Post model and templates. Use when asked for a new content type such as projects, team members, events or testimonials.
---

# Add a custom post type

Check first with `wp-timber-starter/content-model` (MCP) that the type or a plugin providing it doesn't already exist.

## 1. Register it: `src/PostTypes/{Name}.php`

```php
<?php

declare(strict_types=1);

namespace WpTimberStarter\PostTypes;

final class Project
{
    public const NAME = 'project';

    public function __construct()
    {
        add_action('init', [$this, 'register']);
    }

    public function register(): void
    {
        register_post_type(self::NAME, [
            'labels' => [
                'name' => __('Projects', 'wp-timber-starter'),
                'singular_name' => __('Project', 'wp-timber-starter'),
            ],
            'public' => true,
            'has_archive' => true,
            'show_in_rest' => true, // block editor + REST
            'menu_icon' => 'dashicons-portfolio',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'rewrite' => ['slug' => 'projects'],
        ]);
    }
}
```

Instantiate it in `functions.php`: `new WpTimberStarter\PostTypes\Project;`

Taxonomy: same pattern in `src/Taxonomies/`, `register_taxonomy()` on `init`.

## 2. Model (only if it needs behaviour): `src/Models/{Name}.php`

```php
<?php

declare(strict_types=1);

namespace WpTimberStarter\Models;

use Timber\Post;

final class Project extends Post
{
    public function client(): string
    {
        return (string) $this->meta('client');
    }
}
```

Map it in the post type class from step 1, so Timber returns this model for every `project` post:

```php
public function __construct()
{
    add_action('init', [$this, 'register']);
    add_filter('timber/post/classmap', [$this, 'add_to_classmap']);
}

/**
 * @param array<string, mixed>  $classmap
 * @return array<string, mixed>
 */
public function add_to_classmap(array $classmap): array
{
    // Add to the map, don't replace it: Timber maps attachments here too.
    $classmap[self::NAME] = \WpTimberStarter\Models\Project::class;

    return $classmap;
}
```

In Twig: `{{ post.client }}`.

## 3. Queries (only if used in more than one place): `src/Repositories/{Name}Repository.php`

```php
<?php

declare(strict_types=1);

namespace WpTimberStarter\Repositories;

use Timber\PostCollectionInterface;
use Timber\Timber;
use WpTimberStarter\PostTypes\Project;

final class ProjectRepository
{
    public function latest(int $limit): ?PostCollectionInterface
    {
        return Timber::get_posts([
            'post_type' => Project::NAME,
            'posts_per_page' => $limit,
            'no_found_rows' => true, // Not paginated: skip counting every row.
        ]);
    }
}
```

In a root PHP file: `$context['latest_projects'] = (new ProjectRepository())->latest(3);`

To pick "featured" projects, use a taxonomy term, not a meta field: meta queries get slow as content grows. See the Performance section in `AGENTS.md`.

## 4. Templates

- Single: `views/templates/single-project.twig` (picked up by `single.php` automatically).
- Archive: `index.php` handles it; add `views/templates/archive-project.twig` only if it must differ, and render it from an `archive-project.php`.

## 5. Finish

- Tell the user to re-save **Settings → Permalinks** once (flushes rewrite rules). Never call `flush_rewrite_rules()` on every request.
- `composer lint` passes.
