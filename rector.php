<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetList;

/*
 | Rector — automated refactoring & framework-upgrade rules.
 |
 |   lando composer rector:dry   # preview changes, touches nothing
 |   lando composer rector       # apply changes in place
 |
 | Run it on demand (not in the pre-commit hook): review its diffs, then commit.
 */

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    // Target the PHP version declared in composer.json (^8.3).
    ->withPhpSets()
    // High-signal, low-risk rule sets.
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    )
    // Laravel-aware refactors (e.g. modern facade/helper usage).
    ->withSets([
        LaravelSetList::LARAVEL_CODE_QUALITY,
    ]);
