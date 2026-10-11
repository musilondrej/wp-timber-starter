<?php

declare(strict_types=1);

namespace WpTimberStarter;

use WP;
use WP_Admin_Bar;
use WP_Error;
use WP_Query;
use WP_Sitemaps_Provider;

/**
 * Each measure is one hook: delete its line to turn it off.
 */
final class Security
{
    public function __construct()
    {
        add_filter('the_generator', '__return_empty_string');

        // Delete these three if the site uses Jetpack or the WordPress mobile apps.
        add_filter('xmlrpc_methods', '__return_empty_array');
        add_filter('wp_headers', [$this, 'remove_pingback_header']);
        remove_action('wp_head', 'rsd_link');

        // Author archives answer at /author/{username}/, so guessing URLs confirms accounts.
        // Delete on sites that publish author pages; block_author_id_queries then still applies.
        add_filter('pre_handle_404', [$this, 'disable_author_archives'], 10, 2);
        add_action('template_redirect', [$this, 'block_author_id_queries'], 9);
        add_filter('rest_endpoints', [$this, 'hide_users_endpoint_from_guests']);
        add_filter('wp_sitemaps_add_provider', [$this, 'remove_users_sitemap'], 10, 2);
        add_filter('wp_login_errors', [$this, 'hide_login_error_details']);
        add_filter('oembed_response_data', [$this, 'hide_author_in_oembed']);

        // On a 404 WordPress redirects to the first post whose slug starts like the URL
        // (post_name LIKE 'slug%', no index): the wrong page, and a slow query anyone can trigger.
        add_filter('do_redirect_guess_404_permalink', '__return_false');

        // The templates don't show comments, so open ones would only collect spam.
        add_filter('comments_open', '__return_false');
        add_filter('pings_open', '__return_false');
        add_filter('feed_links_show_comments_feed', '__return_false');
        add_action('admin_menu', [$this, 'remove_comments_menu']);
        add_action('admin_bar_menu', [$this, 'remove_comments_from_admin_bar'], 61); // Core adds it at 60.

        add_filter('map_meta_cap', [$this, 'disable_file_editors'], 10, 2);

        // Delete if your web server already sends these headers.
        add_filter('wp_headers', [$this, 'add_security_headers'], 10, 2);
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    public function remove_pingback_header(array $headers): array
    {
        unset($headers['X-Pingback']);

        return $headers;
    }

    public function disable_author_archives(bool $preempt, WP_Query $query): bool
    {
        if (!$query->is_author()) {
            return $preempt;
        }

        $query->set_404();
        status_header(404);
        nocache_headers();

        return true;
    }

    /**
     * redirect_canonical() would send ?author=1 to /author/{username}/. With plain
     * permalinks ?author= is the only author URL, so delete the hook there.
     */
    public function block_author_id_queries(): void
    {
        if (!isset($_GET['author']) || is_user_logged_in()) {
            return;
        }

        wp_safe_redirect(home_url('/'), 301);
        exit;
    }

    /**
     * The block editor needs it, so only guests lose it.
     *
     * @param array<string, mixed> $endpoints
     * @return array<string, mixed>
     */
    public function hide_users_endpoint_from_guests(array $endpoints): array
    {
        if (!is_user_logged_in()) {
            unset($endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)']);
        }

        return $endpoints;
    }

    public function remove_users_sitemap(WP_Sitemaps_Provider|false $provider, string $name): WP_Sitemaps_Provider|false
    {
        return $name === 'users' ? false : $provider;
    }

    public function hide_login_error_details(WP_Error $errors): WP_Error
    {
        $revealing = ['invalid_username', 'invalid_email', 'incorrect_password'];

        if (array_intersect($errors->get_error_codes(), $revealing) === []) {
            return $errors;
        }

        return new WP_Error('incorrect_password', __('Incorrect username or password.', 'wp-timber-starter'));
    }

    /**
     * The oEmbed endpoint stays on even without discovery links, and names the author
     * with a link to /author/{username}/. Credit the site instead, as WordPress does
     * for posts without an author.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function hide_author_in_oembed(array $data): array
    {
        $data['author_name'] = get_bloginfo('name');
        $data['author_url'] = home_url();

        return $data;
    }

    public function remove_comments_menu(): void
    {
        remove_menu_page('edit-comments.php');
    }

    public function remove_comments_from_admin_bar(WP_Admin_Bar $admin_bar): void
    {
        $admin_bar->remove_node('comments');
    }

    /**
     * @param list<string> $caps
     * @return list<string>
     */
    public function disable_file_editors(array $caps, string $cap): array
    {
        if (in_array($cap, ['edit_files', 'edit_plugins', 'edit_themes'], true)) {
            $caps[] = 'do_not_allow';
        }

        return $caps;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    public function add_security_headers(array $headers, WP $wp): array
    {
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';

        // /embed/ URLs exist to be framed by other sites.
        if (!isset($wp->query_vars['embed'])) {
            $headers['X-Frame-Options'] = 'SAMEORIGIN';
        }

        return $headers;
    }
}
