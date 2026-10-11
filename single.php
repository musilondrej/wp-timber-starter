<?php

use Timber\Timber;

defined('ABSPATH') || exit;

$context = Timber::context();
$type = $context['post']->post_type;
$slug = $context['post']->slug;

Timber::render([
    "templates/single-$type-$slug.twig",
    "templates/single-$type.twig",
    'templates/single.twig',
], $context);
