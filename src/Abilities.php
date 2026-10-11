<?php

declare(strict_types=1);

namespace WpTimberStarter;

use Timber\Timber;
use WP_Post;

/**
 * Never return option values or anything else that could hold a secret.
 */
final class Abilities
{
    private const NAMESPACE = 'wp-timber-starter';

    public function __construct()
    {
        add_action('wp_abilities_api_categories_init', [$this, 'register_category']);
        add_action('wp_abilities_api_init', [$this, 'register_abilities']);
    }

    public function register_category(): void
    {
        wp_register_ability_category(self::NAMESPACE, [
            'label' => __('Theme introspection', 'wp-timber-starter'),
            'description' => __('Read-only information about how this theme and site are built, for AI agents.', 'wp-timber-starter'),
        ]);
    }

    /**
     * Descriptions are instructions for agents, so they aren't translated.
     */
    public function register_abilities(): void
    {
        $this->register('site-context', [
            'label' => __('Site context', 'wp-timber-starter'),
            'description' => 'Versions (WordPress, PHP, Timber), environment, active theme and plugins. Call this first to know what you are working with.',
            'execute_callback' => [$this, 'site_context'],
        ]);

        $this->register('content-model', [
            'label' => __('Content model', 'wp-timber-starter'),
            'description' => 'Post types, taxonomies, menu locations and ACF field groups (names and types, never values). Use before adding templates, fields or queries.',
            'execute_callback' => [$this, 'content_model'],
        ]);

        $this->register('template-map', [
            'label' => __('Template map', 'wp-timber-starter'),
            'description' => 'For a URL on this site: which post it resolves to and which Twig templates are tried, in order, with the one that renders. Use before editing a page\'s template.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'url' => ['type' => 'string', 'description' => 'Absolute URL or path on this site, e.g. "/about/".'],
                ],
                'required' => ['url'],
                'additionalProperties' => false,
            ],
            'execute_callback' => [$this, 'template_map'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function site_context(): array
    {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = [];
        foreach (get_option('active_plugins', []) as $file) {
            $data = get_plugin_data(WP_PLUGIN_DIR . '/' . $file, false, false);
            $plugins[] = ['name' => $data['Name'], 'version' => $data['Version'], 'file' => $file];
        }

        $theme = wp_get_theme();

        return [
            'wordpress' => get_bloginfo('version'),
            'php' => PHP_VERSION,
            'timber' => Timber::VERSION,
            'environment' => wp_get_environment_type(),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'locale' => get_locale(),
            'permalinks' => (string) get_option('permalink_structure'),
            'theme' => [
                'name' => $theme->get('Name'),
                'version' => (string) $theme->get('Version'),
                'directory' => get_template_directory(),
            ],
            'plugins' => $plugins,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function content_model(): array
    {
        $post_types = [];
        foreach (get_post_types(['show_ui' => true], 'objects') as $type) {
            $post_types[] = [
                'name' => $type->name,
                'label' => $type->label,
                'public' => $type->public,
                'hierarchical' => $type->hierarchical,
                'supports' => array_keys(get_all_post_type_supports($type->name)),
                'taxonomies' => get_object_taxonomies($type->name),
            ];
        }

        $taxonomies = [];
        foreach (get_taxonomies(['show_ui' => true], 'objects') as $taxonomy) {
            $taxonomies[] = [
                'name' => $taxonomy->name,
                'label' => $taxonomy->label,
                'hierarchical' => $taxonomy->hierarchical,
                'post_types' => $taxonomy->object_type,
            ];
        }

        $assigned = get_nav_menu_locations();
        $menus = [];
        foreach (get_registered_nav_menus() as $location => $label) {
            $menu = isset($assigned[$location]) ? wp_get_nav_menu_object($assigned[$location]) : false;
            $menus[] = ['location' => $location, 'label' => $label, 'menu' => $menu ? $menu->name : null];
        }

        return [
            'post_types' => $post_types,
            'taxonomies' => $taxonomies,
            'menus' => $menus,
            'acf_field_groups' => $this->acf_field_groups(),
        ];
    }

    /**
     * Static lookup, the request isn't run: archives, search, the posts page and
     * URLs that would 404 are all reported as templates/index.twig.
     *
     * @param array{url: string} $input
     * @return array<string, mixed>
     */
    public function template_map(array $input): array
    {
        $url = str_starts_with($input['url'], '/') ? home_url($input['url']) : $input['url'];
        $post = $this->post_for_url($url);
        $templates = $post === null ? ['templates/index.twig'] : $this->candidates_for($post);
        $views = get_template_directory() . '/views/';

        $candidates = [];
        $renders = null;
        foreach ($templates as $template) {
            $exists = file_exists($views . $template);
            $candidates[] = ['template' => $template, 'exists' => $exists];

            if ($exists && $renders === null) {
                $renders = $template;
            }
        }

        return [
            'url' => $url,
            'post' => $post === null ? null : [
                'id' => $post->ID,
                'type' => $post->post_type,
                'slug' => $post->post_name,
                'title' => $post->post_title,
            ],
            'candidates' => $candidates,
            'renders' => $renders,
        ];
    }

    private function post_for_url(string $url): ?WP_Post
    {
        if (untrailingslashit($url) === untrailingslashit(home_url())) {
            $post_id = get_option('show_on_front') === 'page' ? (int) get_option('page_on_front') : 0;
        } else {
            $post_id = url_to_postid($url);
        }

        // The "Posts page" lists posts, so index.php renders it.
        if ($post_id === 0 || $post_id === (int) get_option('page_for_posts')) {
            return null;
        }

        return get_post($post_id);
    }

    /**
     * Keep in sync with page.php and single.php.
     *
     * @return list<string>
     */
    private function candidates_for(WP_Post $post): array
    {
        if ($post->post_type === 'page') {
            return ["templates/page-{$post->post_name}.twig", 'templates/page.twig'];
        }

        return [
            "templates/single-{$post->post_type}-{$post->post_name}.twig",
            "templates/single-{$post->post_type}.twig",
            'templates/single.twig',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function acf_field_groups(): array
    {
        if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
            return [];
        }

        $groups = [];
        foreach (acf_get_field_groups() as $group) {
            $fields = [];
            foreach (acf_get_fields($group) ?: [] as $field) {
                $fields[] = ['name' => $field['name'], 'type' => $field['type'], 'label' => $field['label']];
            }

            $groups[] = [
                'key' => $group['key'],
                'title' => $group['title'],
                'location' => $group['location'],
                'fields' => $fields,
            ];
        }

        return $groups;
    }

    /**
     * @param lowercase-string&non-empty-string $name
     * @param array<string, mixed> $args
     */
    private function register(string $name, array $args): void
    {
        wp_register_ability(self::NAMESPACE . '/' . $name, $args + [
            'category' => self::NAMESPACE,
            'permission_callback' => static fn(): bool => current_user_can('manage_options'),
            'meta' => [
                'annotations' => ['readonly' => true, 'destructive' => false, 'idempotent' => true],
                'show_in_rest' => true,
                'mcp' => ['public' => true],
            ],
        ]);
    }
}
