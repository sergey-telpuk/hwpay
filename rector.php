<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/migrations',
    ])
    ->withPhpVersion(PhpVersion::PHP_85)
    ->withPhpSets(php85: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
        carbon: true,
        phpunitCodeQuality: true,
    )
    ->withComposerBased(phpunit: true)
    ->withImportNames(
        importShortClasses: false,
        removeUnusedImports: true
    )
    ->withSkip([
        __DIR__ . '/vendor',
        __DIR__ . '/var',
    ]);
