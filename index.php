<?php

use Timber\Timber;

defined('ABSPATH') || exit;

$context = Timber::context();

if (is_search()) {
    /* translators: %s: search query */
    $context['title'] = sprintf(__('Search results for "%s"', 'wp-timber-starter'), get_search_query());
} elseif (is_archive()) {
    $context['title'] = get_the_archive_title();
} elseif (is_home() && get_option('page_for_posts')) {
    $context['title'] = get_the_title(get_option('page_for_posts'));
}

Timber::render('templates/index.twig', $context);
