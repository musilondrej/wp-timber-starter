<?php

use Timber\Timber;
use WpTimberStarter\Abilities;
use WpTimberStarter\Environment;
use WpTimberStarter\Security;
use WpTimberStarter\Theme;

defined('ABSPATH') || exit;

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    wp_die('This theme is missing its PHP dependencies. Run <code>composer install</code> in the theme folder.');
}

require_once __DIR__ . '/vendor/autoload.php';

Timber::init();

new Theme();
new Security();
new Environment();
new Abilities();
