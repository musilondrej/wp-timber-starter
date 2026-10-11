<?php

/**
 * Checks what PHP-CS-Fixer and twig-cs-fixer don't: the .editorconfig basics in every text
 * file, and the ABSPATH guard in PHP files that run code when loaded. Class files
 * in src/ only declare classes, so they don't need the guard.
 */
$root = dirname(__DIR__);
$skip = '#^(vendor|node_modules|dist|build|languages|\.git)/|(composer\.lock|package-lock\.json)$#';
$text = '/\.(php|twig|css|js|json|ya?ml|md)$/';
$guarded = '#^(page-templates/)?[^/.][^/]*\.php$#';

$errors = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

foreach ($files as $file) {
    $path = substr($file->getPathname(), strlen($root) + 1);

    if (preg_match($skip, $path) || !preg_match($text, $path)) {
        continue;
    }

    $content = file_get_contents($file->getPathname());

    if ($content === false) {
        $errors[] = "$path: can't be read";
        continue;
    }

    if (str_contains($content, "\r")) {
        $errors[] = "$path: use LF line endings";
    }
    if ($content !== '' && !str_ends_with($content, "\n")) {
        $errors[] = "$path: add a final newline";
    }
    if (preg_match('/^\t/m', $content)) {
        $errors[] = "$path: indent with spaces";
    }
    if (!str_ends_with($path, '.md') && preg_match('/[ \t]$/m', $content)) {
        $errors[] = "$path: remove trailing whitespace";
    }
    if (preg_match($guarded, $path) && !preg_match("/^defined\('ABSPATH'\) \|\| exit;$/m", $content)) {
        $errors[] = "$path: add defined('ABSPATH') || exit;";
    }
}

foreach ($errors as $error) {
    fwrite(STDERR, "✗ $error\n");
}

echo $errors ? '' : "✓ Files follow .editorconfig and have ABSPATH guards\n";
exit($errors ? 1 : 0);
