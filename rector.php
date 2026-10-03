<?php

declare(strict_types=1);

use Pest\Rector\Set\PestSetList;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/benchmarks',
        __DIR__.'/samples',
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        phpunitCodeQuality: true,
    )
    ->withSets([
        PestSetList::CODING_STYLE,
    ])
    ->withPhpSets()
    // A method that documents one parameter documents all of them.
    ->withSkip([
        RemoveUselessParamTagRector::class,
    ]);
