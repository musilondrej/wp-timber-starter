<?php

declare(strict_types=1);

namespace WpTimberStarter;

use Timber\Timber;

final class Theme
{
    public function __construct()
    {
        add_action('after_setup_theme', [$this, 'setup']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('timber/context', [$this, 'add_to_context']);
        add_filter('timber/twig/environment/options', [$this, 'twig_options']);

        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'wp_oembed_add_discovery_links'); // Other sites can't embed your posts without it.

        add_filter('should_load_remote_block_patterns', '__return_false');
        remove_action('enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets');
        add_filter('block_editor_settings_all', [$this, 'limit_editor_for_non_admins']);
        add_filter('mce_css', [$this, 'keep_theme_css_out_of_classic_editor']);

        add_filter('acf/settings/save_json', [$this, 'acf_json_path']);
        add_filter('acf/settings/load_json', [$this, 'add_acf_json_path']);
        add_action('acf/init', [$this, 'register_options_page']);

        // Field groups are edited locally and deployed as JSON. Edits on production
        // would drift from git and be overwritten by the next deploy.
        add_filter('acf/settings/show_admin', [$this, 'show_acf_admin']);
    }

    public function setup(): void
    {
        load_theme_textdomain('wp-timber-starter', get_template_directory() . '/languages');

        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('automatic-feed-links');
        add_theme_support('html5', ['search-form', 'gallery', 'caption']);
        add_theme_support('editor-styles');
        add_editor_style('dist/app.css');
        remove_theme_support('core-block-patterns');

        register_nav_menus([
            'primary' => __('Primary menu', 'wp-timber-starter'),
            'footer' => __('Footer menu', 'wp-timber-starter'),
        ]);
    }

    public function enqueue_assets(): void
    {
        $path = get_template_directory() . '/dist';
        $url = get_template_directory_uri() . '/dist';

        if (file_exists("$path/app.css")) {
            wp_enqueue_style('wp-timber-starter', "$url/app.css", [], (string) filemtime("$path/app.css"));
            wp_style_add_data('wp-timber-starter', 'path', "$path/app.css"); // Lets WordPress inline it while it's under 40 KB.
        }

        if (file_exists("$path/app.js")) {
            wp_enqueue_script('wp-timber-starter', "$url/app.js", [], (string) filemtime("$path/app.js"), ['strategy' => 'defer']);
        }
    }

    /**
     * Without the code editor and block locking, editors can't break a layout the
     * theme builds from locked blocks or patterns.
     *
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public function limit_editor_for_non_admins(array $settings): array
    {
        if (current_user_can('activate_plugins')) {
            return $settings;
        }

        $settings['codeEditingEnabled'] = false;
        $settings['canLockBlocks'] = false;

        return $settings;
    }

    /**
     * add_editor_style() also feeds TinyMCE (ACF WYSIWYG fields), where a CSS framework's
     * reset strips list bullets and link underlines. The block editor keeps the styles.
     */
    public function keep_theme_css_out_of_classic_editor(string $stylesheets): string
    {
        $theme_css = get_template_directory_uri() . '/dist/app.css';
        $kept = array_filter(explode(',', $stylesheets), fn(string $url) => !str_starts_with($url, $theme_css));

        return implode(',', $kept);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function add_to_context(array $context): array
    {
        $context['primary_menu'] = Timber::get_menu('primary');
        $context['footer_menu'] = Timber::get_menu('footer');

        if (function_exists('get_field')) {
            $context['options'] = new Options();
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function twig_options(array $options): array
    {
        $options['autoescape'] = 'html';

        // Timber's default cache lives in vendor/, which `composer install` wipes. On hosts
        // where wp-content isn't writable Twig would throw, so compile without a cache there.
        // auto_reload keeps a deploy from serving stale compiled templates.
        $cache = WP_CONTENT_DIR . '/cache/timber';
        $options['cache'] = wp_mkdir_p($cache) && wp_is_writable($cache) ? $cache : false;
        $options['auto_reload'] = true;

        return $options;
    }

    public function acf_json_path(): string
    {
        return get_template_directory() . '/acf-json';
    }

    /**
     * @param list<string> $paths
     * @return list<string>
     */
    public function add_acf_json_path(array $paths): array
    {
        $paths[] = $this->acf_json_path();

        return $paths;
    }

    public function show_acf_admin(): bool
    {
        return wp_get_environment_type() !== 'production';
    }

    public function register_options_page(): void
    {
        if (!function_exists('acf_add_options_page')) {
            return;
        }

        acf_add_options_page([
            'page_title' => __('Theme settings', 'wp-timber-starter'),
            'menu_slug' => 'theme-settings',
            'capability' => 'manage_options',
            'redirect' => false,
        ]);
    }
}
