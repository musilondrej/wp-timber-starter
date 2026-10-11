# AGENTS.md

WordPress theme on **Timber 2** (Twig). PHP 8.2+, WordPress 7.0+. Text domain: `wp-timber-starter`.

## Commands

All must pass before a task is done.

| Task | Command |
| --- | --- |
| Install | `composer install && npm ci` |
| Build assets | `npm run build` (watch: `npm run dev`) |
| Format | `composer format`: PHP-CS-Fixer (PER Coding Style) and twig-cs-fixer (Twig coding standard). Never format by hand. |
| Lint | `composer lint`: PHP-CS-Fixer, PHPStan level 8, Twig style and syntax, `.editorconfig` rules, ABSPATH guards |
| Audit | `composer audit && npm audit --omit=dev` |
| Translations | `composer i18n` after adding or changing strings |
| Production zip | `npm run build:zip` (a `v*` tag builds it in CI and attaches it to a GitHub release) |

## Layout

- `functions.php`: bootstrap only. Each feature is a class in `src/` (PSR-4, `WpTimberStarter\`), instantiated there.
- Root `*.php`: template hierarchy entry points. They build the context and call `Timber::render()`, nothing else.
- `views/`: `layouts/base.twig`, `templates/` (what root PHP files render: `page.twig`, `single-{post_type}.twig`, …), `partials/`.
- `assets/{css,js}/`: sources. `assets/css/base.css` (WordPress classes, focus, reduced motion, menu toggle) is imported by every preset; keep it framework-free. `acf-json/`: ACF field groups, written by ACF when saved in wp-admin. `languages/`: translations.
- Never edit `dist/`, `vendor/`, `node_modules/` or WordPress core.

## Timber 2

- `Timber::get_post()`, `Timber::get_posts([...])`, `Timber::get_term()`, `Timber::get_menu()`. Never `new Timber\Post`, `Timber::get_context()` or query strings (Timber 1).
- `Timber::context()` once per request, in the root PHP file. Elsewhere `Timber::context_global()`.
- Global data goes in `Theme::add_to_context()`. It runs on every page: keep it cheap.
- Meta: `post.meta('field')` / `$post->meta('field')`. Not `post.field`, not `get_field()` in templates.
- Post behaviour: a `Timber\Post` subclass added to the `timber/post/classmap` filter (merge, don't replace).
- Pagination: `posts.pagination`, never `Timber::get_pagination()`.

## Escaping

Autoescape is on: every `{{ }}` is HTML-escaped. Opt out only for trusted HTML.

| Output | Write |
| --- | --- |
| Values, attributes, `post.link` | `{{ value }}` |
| Titles (`post.title`, `item.title`, `site.name`), they contain entities | `{{ post.title\|wp_kses_post }}` |
| Editor HTML (`post.content`, `post.excerpt`) | `{{ post.content\|raw }}` |
| URLs WordPress already escaped (pagination) | `{{ link\|esc_url }}` |
| WP functions that echo (`wp_head`) | `{{ function('wp_head') }}` |
| HTML returned by WP functions | `{{ function('wp_get_attachment_image', image.id, 'large')\|raw }}` |

Use `|wp_kses_post`, not `|e('wp_kses_post')` (double-escapes). Never `|raw` on user input.

## Twig

- Templates display data; they don't compute it. No queries, no WordPress functions except the escaping table's, no business rules in `{% if %}`.
- Partials use the global context (`site`, menus) or explicit arguments: `{{ include('partials/tease.twig', {post: post}) }}`, never a variable that happens to exist in the caller. Layout slots are `{% block %}`s; a partial with slots is an `{% embed %}`.
- Small repeated markup (icon, button) is a macro in `views/macros/`, imported with `{% import %}`.
- Custom functions and filters are methods of a class in `src/Twig/`, registered through the `timber/twig/functions` and `timber/twig/filters` filters.
- Variables and macro names are `snake_case`. Template file names follow the WordPress hierarchy (`single-project.twig`).

## WordPress

- Translate every user-facing string, PHP and Twig: `__('Text', 'wp-timber-starter')`, English source. Then `composer i18n` and translate the new entries in `languages/*.po`.
- Assets only through `Theme::enqueue_assets()`. No inline `<script>` or `<style>`. The block editor loads `dist/app.css` too (`editor-styles`), so styles must work inside it.
- Images only through `wp_get_attachment_image()` (srcset, sizes, lazy loading, plugin hooks). Only the main image above the fold gets `{ fetchpriority: 'high', loading: false }`.
- `current_user_can()` for anything privileged; a nonce on every form or AJAX call that writes.
- `src/Environment.php` keeps non-production sites out of search engines; never index a site whose `WP_ENVIRONMENT_TYPE` isn't `production`. Non-admins can't use the code editor or unlock blocks (`Theme::limit_editor_for_non_admins()`), so build client-facing layouts from locked blocks or patterns.
- `src/Security.php` holds the hardening. Don't weaken it to make a feature work: remove the single hook in the way and say so. SEO, forms, page caching, firewalls and rate limiting are plugin jobs, not theme code.
- `theme.json` (version 3) when a site needs editor presets. The theme ships without one because it makes WordPress print default global styles that clash with the CSS presets; check the front end after adding it. Preset slugs get hyphens in CSS variables: `3xl` → `--wp--preset--font-size--3-xl`.
- Patterns: `patterns/*.php` with a file header, registered automatically.
- Simple blocks: PHP-only (`'supports' => ['autoRegister' => true]`, registered on `init`, class in `src/Blocks/`), `render_callback` returns `Timber::compile('blocks/{name}.twig', …)`. Scalar attributes only, no inner blocks; anything richer is an ACF or `block.json` block.
- Accessibility (WCAG 2.2 AA): visible focus, keyboard-operable controls with `aria-expanded`/`aria-controls` where they toggle something, text alternatives, and visually hidden text via `.screen-reader-text`.
- Interactivity: small behaviour is plain JS modules in `assets/js/`, imported from `app.js`. Server-driven state uses the Interactivity API: `data-wp-*` in Twig, `wp_interactivity_state()`, a store enqueued with `wp_enqueue_script_module()`, and the outermost HTML passed once through `wp_interactivity_process_directives()`.

## Performance

- Queries: arguments arrays with `posts_per_page`, never `-1` on unbounded content. `'no_found_rows' => true` unless the list paginates. `'update_post_meta_cache' => false` / `'update_post_term_cache' => false` only when the list reads no meta or terms; otherwise leave them on, which loads them for all posts at once instead of one query per post.
- No `meta_query` on lists that grow, no `post__not_in`, no `'orderby' => 'rand'`, no queries across several taxonomies at once. Use a taxonomy, or filter the smaller result in PHP.
- A slow query that isn't the main one: cache its post IDs (`'fields' => 'ids'`) with `wp_cache_get()`/`wp_cache_set()` or a transient, clear it on `save_post_{post_type}`, then `Timber::get_posts($ids)`. `Timber::render()`'s `$expires` only caches the HTML; the query has already run to build the context.
- Expensive data that isn't a post list: `Timber\Helper::transient($key, $callback, $seconds)`, which locks against parallel rebuilds. Always pass `$seconds`; `0` means forever.
- Transients always expire. Large options get `autoload` off.
- Remote requests (`wp_remote_get()`): an explicit `timeout`, cached, never on every page load.
- Front-end HTML is the same for every guest, so page caches work: no output based on `$_COOKIE` or sessions (do personal bits client-side), no database writes while rendering, custom endpoints through the REST API, not `admin-ajax.php`.
- Check new templates with Query Monitor for repeated queries.

## PHP

- Root PHP files and page templates have `defined('ABSPATH') || exit;` after their `use` lines. `src/` class files don't: they only declare classes.
- Every `src/` file: `declare(strict_types=1);`, one class/enum/trait, file name = type name.
- Classes are `final`. Type all properties, parameters and returns; PHPDoc only for array shapes and non-obvious reasons.
- One job per class, named after it (`ProjectArchive`, not `Helpers`). Collaborators come through the constructor; `functions.php` wires them. No singletons, static state or containers. `static` only for named constructors.
- No interface, abstract class or factory with one implementation.
- `snake_case` methods, properties and variables (like WordPress, and Twig reads `{{ project.client_name }}`); `PascalCase` classes and enum cases; `UPPER_CASE` constants.
- Small methods, early returns, no `else` after `return`.
- PHP 8.2 features only: no typed class constants, `#[\Override]`, `json_validate()` or property hooks.
- Fix PHPStan errors at the cause. No `@phpstan-ignore`, baseline or casts to silence it.

Folders appear when first needed: `PostTypes/`, `Taxonomies/` (registration), `Models/` (`Timber\Post` subclasses: a content type's behaviour), `Repositories/` (its queries: `ProjectRepository::latest(int $limit)`), `Data/` (readonly DTOs), `Enums/`, `Blocks/`, `Twig/`, `Concerns/` (small stateless traits).

### Typed data at the boundary

WordPress returns arrays with unknown keys, numbers as strings, and `false` or `WP_Error` on failure. Convert that once, where it enters the theme; past that point, Twig included, everything is typed.

```php
final readonly class ContactDetails
{
    public function __construct(public string $phone, public string $email) {}

    /** @param array<string, mixed> $fields */
    public static function from_acf(array $fields): self
    {
        return new self(
            phone: (string) ($fields['phone'] ?? ''),
            email: sanitize_email((string) ($fields['email'] ?? '')),
        );
    }
}
```

- Arrays are lists. A record with known keys is a DTO, except arguments WordPress wants as arrays.
- Check `WP_Error`, `false` and `null` where you call WordPress. Your methods return `null` or `[]` for nothing, never `false`.
- Fixed value sets are backed enums.
- Missing data on the front end hides the section; it doesn't throw. Exceptions are for programming errors.
- `options` in the context reads ACF option fields lazily (`src/Options.php`). Never `get_fields('option')`: it runs an uncached LIKE query over `wp_options` on every request. Once a site's options are settled, wrap them in a DTO.

### Where logic goes

- Content-type behaviour: its model (`$project->client()`), not Twig or root PHP files.
- Queries: a repository per content type, created where it's needed (`new ProjectRepository()` in a root PHP file) or passed through a constructor. No `Timber::get_posts([...])` arrays copied between files.
- No `global`, `$GLOBALS` or `extract()`.

### Hooks

- Registered in the constructor. Callbacks are public typed methods (`[$this, 'method']`) or core helpers (`'__return_false'`). No closures: `remove_action()` can't remove them.
- Named after what they do (`enqueue_assets`), not the hook.

## Dependencies

- Ask before adding a Composer or npm package.
- `.npmrc` and Composer's audit config block risky installs. Don't weaken them to make an install pass.
- Pin exact versions; commit lockfiles.

## AI tooling

- **MCP:** abilities `wp-timber-starter/site-context`, `wp-timber-starter/content-model`, `wp-timber-starter/template-map` (`src/Abilities.php`). Use them before guessing versions, the content model or which template renders a URL. Needs `WP_PATH` and the MCP Adapter plugin (`.mcp.json`); if the server isn't connected, say so. New abilities: all three annotations, `readonly` only if nothing is written, `additionalProperties: false`, descriptions on required inputs, `WP_Error` codes like `{slug}_invalid_{field}`.
- **Skills:** `.agents/skills/` (also `.claude/skills`) for templates, post types, ACF fields and releases. For other WordPress topics, use the official `WordPress/agent-skills` if installed; they're GPL, never copy them into this repo.

## Before you finish

1. `composer lint` passes; `npm run build` too if `assets/` changed.
2. New user-facing strings are translatable.
3. No generated or vendored files edited.
