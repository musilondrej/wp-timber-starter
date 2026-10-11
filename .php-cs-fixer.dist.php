<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['node_modules', 'dist', 'build', 'languages'])
    ->append([__DIR__ . '/bin/setup']);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        'unary_operator_spaces' => true,
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'phpdoc_align' => ['align' => 'left'],
    ])
    ->setFinder($finder);
