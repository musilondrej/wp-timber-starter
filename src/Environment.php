<?php

declare(strict_types=1);

namespace WpTimberStarter;

use WP_Admin_Bar;

/**
 * Local and staging sites: kept out of search engines and labelled in the admin bar.
 * Set WP_ENVIRONMENT_TYPE in wp-config.php; WordPress treats an unset one as production.
 */
final class Environment
{
    private readonly string $type;

    public function __construct()
    {
        $this->type = wp_get_environment_type();

        if ($this->type === 'production') {
            return;
        }

        // Not the "Discourage search engines" setting: a database copied from
        // production brings that setting along, switched off.
        add_filter('wp_robots', 'wp_robots_no_robots');
        add_filter('wp_headers', [$this, 'add_noindex_header']);

        add_action('admin_bar_menu', [$this, 'add_admin_bar_badge'], 0);
        add_action('wp_enqueue_scripts', [$this, 'style_admin_bar_badge']);
        add_action('admin_enqueue_scripts', [$this, 'style_admin_bar_badge']);
    }

    /**
     * Covers responses without a <head>: feeds, sitemaps, robots.txt.
     *
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    public function add_noindex_header(array $headers): array
    {
        $headers['X-Robots-Tag'] = 'noindex, nofollow';

        return $headers;
    }

    public function add_admin_bar_badge(WP_Admin_Bar $admin_bar): void
    {
        $admin_bar->add_node([
            'id' => 'environment',
            'title' => strtoupper($this->type),
            'meta' => ['class' => "environment-badge environment-badge--{$this->type}"],
        ]);
    }

    public function style_admin_bar_badge(): void
    {
        if (!is_admin_bar_showing()) {
            return;
        }

        wp_add_inline_style('admin-bar', '
            #wpadminbar .environment-badge > .ab-item { background: #15803d; color: #fff; font-weight: 600; }
            #wpadminbar .environment-badge--staging > .ab-item { background: #c2410c; }
        ');
    }
}
