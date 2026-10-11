<?php

/**
 * Compiles every Twig template in views/ to catch syntax errors, without booting WordPress.
 *
 * Unknown functions and filters (WordPress and Timber ones) are replaced with
 * stubs, so this checks syntax only, not what the templates output.
 *
 * Usage: composer lint:twig
 */

use Twig\Environment;
use Twig\Error\Error;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

require dirname(__DIR__) . '/vendor/autoload.php';

$views = dirname(__DIR__) . '/views';
$twig = new Environment(new FilesystemLoader($views));

// Any unknown function returns '' and any unknown filter returns its input unchanged.
$twig->registerUndefinedFunctionCallback(fn(string $name) => new TwigFunction($name, fn() => ''));
$twig->registerUndefinedFilterCallback(fn(string $name) => new TwigFilter($name, fn($value) => $value));

$failed = 0;
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views, FilesystemIterator::SKIP_DOTS));

foreach ($files as $file) {
    if ($file->getExtension() !== 'twig') {
        continue;
    }

    $name = substr($file->getPathname(), strlen($views) + 1);

    try {
        $twig->compileSource($twig->getLoader()->getSourceContext($name));
    } catch (Error $e) {
        $failed++;
        fwrite(STDERR, "✗ {$name}:{$e->getTemplateLine()} {$e->getRawMessage()}\n");
    }
}

echo $failed ? "{$failed} template(s) failed\n" : "✓ Twig templates OK\n";
exit($failed ? 1 : 0);
