# WP Timber Starter

A [Timber 2](https://timber.github.io/docs/v2/) starter theme for WordPress: semantic HTML with no design, a CSS framework of your choice, hardened and fast defaults, and guidelines for AI coding agents.

## Getting started

```bash
cd wp-content/themes
composer create-project musilondrej/wp-timber-starter my-theme
cd my-theme && npm install && npm run dev
```

Setup asks for a theme name and a CSS preset, renames the theme, text domain and PHP namespace (`Acme\`), then deletes `bin/` and `stubs/`:

```
  Theme name [WP Timber Starter]: Acme
  CSS framework:
    [1] bootstrap
    [2] none (default)
    [3] pico
    [4] tailwind
  > 4

  ✓ Acme (acme) with tailwind
```

From GitHub instead: click **Use this template**, clone your new repository and run `composer install`, `php bin/setup` (or `php bin/setup --name="Acme" --css=tailwind`) and `npm install`.

| Preset | What you get | `dist/app.css` |
| --- | --- | --- |
| `none` | ~30 lines of base CSS, light/dark via `light-dark()` | ~1 KB |
| `tailwind` | Tailwind CSS v4 + Typography | only the classes you use |
| `bootstrap` | Bootstrap 5.3 CSS (add its JS when you need components) | ~230 KB |
| `pico` | [Pico CSS](https://picocss.com), classless | ~80 KB |

Every preset imports `assets/css/base.css`: the classes WordPress outputs (`.screen-reader-text`, alignments), a visible focus ring, reduced motion, and the mobile menu toggle. A preset is a folder in `stubs/css/<name>/` with `app.css` and `preset.json` (extra `devDependencies` and `scripts` for `package.json`).

## Commands

| Command | |
| --- | --- |
| `npm run dev` | Watch JS and CSS (esbuild; Tailwind uses its CLI) |
| `npm run build` | Minified build into `dist/` |
| `npm run build:zip` | `build/<theme>.zip` without dev files, with an optimized autoloader |
| `composer lint` | [PHP-CS-Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer) (PER Coding Style), [PHPStan](https://phpstan.org) level 8 with WordPress and ACF stubs, [twig-cs-fixer](https://github.com/VincentLanglet/Twig-CS-Fixer), Twig syntax, `.editorconfig` rules and ABSPATH guards |
| `composer format` | Fix PHP and Twig code style |
| `composer i18n` | Extract strings from PHP and Twig into `languages/theme.pot`, update the `.po` files, compile `.mo` and `.l10n.php` |

Locally, [Query Monitor](https://wordpress.org/plugins/query-monitor/) shows duplicate and slow queries per page; check new templates with it. `composer install` turns on a pre-commit hook (`.githooks/`) that runs `composer lint`. CI runs lint, `composer audit` and the asset build on PHP 8.2 and 8.4. Pushing a `v*` tag builds the production zip and attaches it to a GitHub release. Actions are pinned to commit SHAs; Dependabot sends weekly grouped updates with a 7-day cooldown.

## Structure

```
src/Theme.php        supports, menus, assets, Twig settings, ACF JSON and options page
src/Security.php     hardening
src/Environment.php  noindex and an admin bar badge on local and staging sites
src/Abilities.php    read-only abilities for AI agents
views/               layouts/base.twig, templates/, partials/
assets/              CSS and JS sources (setup writes assets/css/app.css)
acf-json/            ACF field groups
languages/           theme.pot and a Czech translation
AGENTS.md            guidelines for AI agents
.agents/skills/      agent playbooks (also .claude/skills/)
```

Pages try `views/templates/page-{slug}.twig`, then `page.twig`. Posts try `single-{type}-{slug}.twig`, `single-{type}.twig`, `single.twig`. Twig autoescaping is on (Timber ships with it off); see the escaping table in `AGENTS.md`.

## Security and performance

The hardening, editor and `<head>` items are single hooks in `src/Security.php`, `src/Environment.php` and `src/Theme.php`; delete the line to turn one off.

| Default | Why |
| --- | --- |
| XML-RPC off, no pingback header or RSD link | Used for password guessing and pingback spam. Keep it for Jetpack or the mobile apps. |
| Author archives return 404; `?author=1`, `/wp/v2/users`, the users sitemap and the oEmbed author link hidden from guests | They reveal usernames. Delete the archive hook on sites with author pages. |
| No redirect guessing on 404 | WordPress would redirect a dead link to the first post with a similar slug, using a slow `LIKE` query anyone can trigger. |
| One login error for wrong username and wrong password | Doesn't confirm which accounts exist. |
| Comments and pingbacks closed, hidden in wp-admin | The templates don't show comments; open ones would only collect spam. |
| File editors disabled | Same as `DISALLOW_FILE_EDIT`. |
| `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` | Drop them if the server already sends them. |
| Local and staging sites are `noindex` (meta tag and `X-Robots-Tag`) and show a LOCAL/STAGING badge in the admin bar | A staging site with a database copied from production can't end up in Google, and nobody edits the wrong site by mistake. |
| Editors and authors can't use the code editor or unlock blocks | Clients can't break layouts; administrators keep the full editor. |
| ACF field group editor hidden on production (`WP_ENVIRONMENT_TYPE`) | Fields are edited locally and deployed as JSON in `acf-json/`, so production never drifts from git. |
| No generic patterns or block directory in the editor | Core patterns don't fit the design; editors can't install plugins from the inserter. |
| Accessible menu toggle, skip link and screen-reader text in every preset | Keyboard and screen-reader users can use the site whatever the CSS framework (European Accessibility Act). |
| WordPress version, emoji script, shortlink and oEmbed links removed | Less `<head>`, no third-party emoji request. |
| Small CSS inlined, deferred JS, `srcset` and `fetchpriority` on the main image, compiled Twig cached | Fewer render-blocking requests, faster LCP. |

Per site, outside the theme:

- `wp-config.php`: `WP_ENVIRONMENT_TYPE` (`local`, `development` or `staging` everywhere except production, which is the default), `DISALLOW_FILE_EDIT` (holds when another theme is active), on production `DISALLOW_FILE_MODS` (plugins and updates only through deploys), `WP_POST_REVISIONS`, and `DISABLE_WP_CRON` with a server cron running `wp cron event run --due-now` every 5 minutes.
- Staging and local: catch outgoing e-mail (e.g. Mailpit) so test runs never mail real users.
- Server: HTTPS with HSTS, a Content-Security-Policy, no PHP execution in `wp-content/uploads/`, `wp-content/cache/` (compiled Twig) or the theme's `src/` and `vendor/`, rate limiting on `wp-login.php`.
- Plugins and services: two-factor login and login rate limiting, backups, page cache, a persistent object cache on busy sites, uptime and error monitoring (e.g. Sentry).

### Supply chain

| Setting | Effect |
| --- | --- |
| `.npmrc` `min-release-age=7` | No npm versions younger than 7 days; most malicious releases are pulled within hours. |
| `.npmrc` `ignore-scripts=true` | No install scripts from dependencies. esbuild and Tailwind don't need them. |
| `.npmrc` `save-exact`, `engine-strict` | Exact versions; npm older than 11.10 refuses to install rather than skip these rules. |
| Composer `audit.block-insecure`, `block-abandoned` | No packages with known advisories or abandoned ones. |
| Composer `allow-plugins` | Only `phpstan/extension-installer` may run code on install. |

## AI agents

- **`AGENTS.md`**: commands, structure, Timber and escaping rules, PHP conventions. Read by Claude Code (2.1.277+), Codex, Cursor, Copilot and others. If you add a `CLAUDE.md`, start it with `@AGENTS.md`, because Claude Code then reads only `CLAUDE.md`.
- **Skills** in `.agents/skills/`: `add-template`, `add-post-type`, `add-acf-fields`, `release`.
- **MCP**: `src/Abilities.php` registers read-only [abilities](https://developer.wordpress.org/apis/abilities-api/) for admins, over the [MCP Adapter](https://github.com/WordPress/mcp-adapter) or REST (`/wp-json/wp-abilities/v1`):

  | Ability | Returns |
  | --- | --- |
  | `wp-timber-starter/site-context` | WordPress, PHP and Timber versions, environment, theme, plugins |
  | `wp-timber-starter/content-model` | Post types, taxonomies, menu locations, ACF field names and types |
  | `wp-timber-starter/template-map` | For a URL: its post, the Twig templates tried and the one that renders |

  To connect Claude Code: activate the MCP Adapter plugin on your local site, `export WP_PATH=/path/to/wordpress` (and `WP_MCP_USER` if the admin isn't `admin`), and start Claude Code in the theme folder.

- **Official WordPress skills** for everything else, installed globally (a project install would land in this repo's `.claude/skills/`, and they're GPL):

  ```bash
  npx skills add WordPress/agent-skills --global --skill wp-block-development wp-interactivity-api wp-abilities-api wp-rest-api wp-performance wp-phpstan wp-playground
  ```

`.claude/settings.json` pre-allows lint and build commands and blocks reading `.env`, editing `vendor/` or `dist/`, and destructive `wp db` commands.

## Requirements

PHP 8.2+, WordPress 7.0+, Composer 2.9+, Node 20.17+ with npm 11.10+.

## License

[MIT](LICENSE). Themes built from WP Timber Starter may use another license (publicly distributed themes are usually GPL, like WordPress) but must keep the `LICENSE` file or its copyright notice.

Dependencies: Timber (MIT), Twig (BSD-3-Clause), Symfony polyfills (MIT). CSS presets: Tailwind CSS, Bootstrap, Pico CSS (all MIT).
