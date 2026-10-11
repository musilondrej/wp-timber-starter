<?php

declare(strict_types=1);

namespace WpTimberStarter;

/**
 * `{{ options.phone }}` reads that one field from the ACF options page, only when a
 * template uses it. get_fields('option') would run an uncached LIKE query over
 * wp_options and format every field on every page load.
 */
final class Options
{
    public function __isset(string $name): bool
    {
        return true;
    }

    public function __get(string $name): mixed
    {
        return get_field($name, 'option');
    }
}
