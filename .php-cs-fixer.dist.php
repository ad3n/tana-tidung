<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use Sibaper\CodingStandard\BlankLineAfterControlBlockFixer;

require_once __DIR__.'/tools/php-cs-fixer/BlankLineAfterControlBlockFixer.php';

$finder = Finder::create()
    ->in([__DIR__.'/app', __DIR__.'/lib', __DIR__.'/tests', __DIR__.'/config', __DIR__.'/tools/php-cs-fixer'])
    ->notPath('reference.php')
    ->exclude('secrets')
    ->append([
        __FILE__,
        __DIR__.'/bin/console',
        __DIR__.'/bin/phpunit',
        __DIR__.'/public/index.php',
        __DIR__.'/phpinsights.php',
        __DIR__.'/rector.php',
    ]);

return (new Config())
    ->setUsingCache(true)
    ->setRiskyAllowed(false)
    ->setFinder($finder)
    ->registerCustomFixers([new BlankLineAfterControlBlockFixer()])
    ->setRules([
        'Sibaper/blank_line_after_control_block' => true,
        '@PSR1' => true,
        '@PSR2' => true,
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'],
        'fully_qualified_strict_types' => ['import_symbols' => true],
        'global_namespace_import' => ['import_classes' => true, 'import_constants' => false, 'import_functions' => false],
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_import_per_statement' => true,
        'single_line_throw' => false,
    ]);
