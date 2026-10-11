<?php

use Timber\Timber;

defined('ABSPATH') || exit;

$context = Timber::context();
$slug = $context['post']->slug;

Timber::render([
    "templates/page-$slug.twig",
    'templates/page.twig',
], $context);
