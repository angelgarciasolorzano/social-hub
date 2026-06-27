<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\PostInc\PostIncDecToPreIncDecRector;
use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use RectorLaravel\Set\LaravelSetList;
use RectorLaravel\Set\LaravelSetProvider;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        naming: true,
        instanceOf: true,
        carbon: true,
        phpunitCodeQuality: true,
        earlyReturn: true,
        rectorPreset: true,
    )
    ->withSkip([
        PostIncDecToPreIncDecRector::class,
    ])
    ->withImportNames(
        removeUnusedImports: true,
        importShortClasses: true,
        importDocBlockNames: true,
    )
    ->withPhpVersion(PhpVersion::PHP_85)
    ->withFluentCallNewLine()
    ->withPhpSets()
    ->withSetProviders(LaravelSetProvider::class)
    ->withComposerBased(laravel: true)
    ->withSets([
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_TYPE_DECLARATIONS,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_FACTORIES,
        LaravelSetList::LARAVEL_TESTING,
        LaravelSetList::LARAVEL_IF_HELPERS,
        LaravelSetList::LARAVEL_ELOQUENT_MAGIC_METHOD_TO_QUERY_BUILDER,
        LaravelSetList::LARAVEL_ARRAY_STR_FUNCTION_TO_STATIC_CALL,
        LaravelSetList::LARAVEL_ARRAYACCESS_TO_METHOD_CALL,
        LaravelSetList::LARAVEL_LEGACY_FACTORIES_TO_CLASSES,
    ]);
