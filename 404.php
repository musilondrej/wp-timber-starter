<?php

use Timber\Timber;

defined('ABSPATH') || exit;

Timber::render('templates/404.twig', Timber::context());
