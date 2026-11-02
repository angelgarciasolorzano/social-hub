<?php

declare(strict_types=1);

use App\Support\Translation\FrontendTranslations;

const FRONTEND_FIXTURE_GROUP = 'fixture/sample';

beforeEach(function (): void {
    resolve('translator')->addLines([
        'fixture/sample.title' => 'Title',
        'fixture/sample.dialog.confirm' => 'Confirm',
        'fixture/sample.dialog.cancel' => 'Cancel',
    ], 'en');

    resolve('translator')->addLines([
        'fixture/sample.title' => 'Título',
        'fixture/sample.dialog.confirm' => 'Confirmar',
    ], 'es');
});

it('returns a registered group in the active locale', function (): void {
    app()->setLocale('en');

    expect(resolve(FrontendTranslations::class)->use(FRONTEND_FIXTURE_GROUP)->toArray())->toBe([
        FRONTEND_FIXTURE_GROUP => ['title' => 'Title', 'dialog' => ['confirm' => 'Confirm', 'cancel' => 'Cancel']],
    ]);
});

it('fills a key missing in the active locale with the fallback text', function (): void {
    app()->setLocale('es');

    expect(resolve(FrontendTranslations::class)->use(FRONTEND_FIXTURE_GROUP)->toArray())->toBe([
        FRONTEND_FIXTURE_GROUP => ['title' => 'Título', 'dialog' => ['confirm' => 'Confirmar', 'cancel' => 'Cancel']],
    ]);
});

it('returns nothing when no group is registered', function (): void {
    expect(resolve(FrontendTranslations::class)->toArray())
        ->toBeEmpty();
});

it('registers a group once even when it is used twice', function (): void {
    $frontendTranslations = resolve(FrontendTranslations::class)->use(FRONTEND_FIXTURE_GROUP, FRONTEND_FIXTURE_GROUP);
    $frontendTranslations->use(FRONTEND_FIXTURE_GROUP);

    expect($frontendTranslations->toArray())->toHaveCount(1)->toHaveKey(FRONTEND_FIXTURE_GROUP);
});

it('keeps the registered groups for the whole request', function (): void {
    resolve(FrontendTranslations::class)->use(FRONTEND_FIXTURE_GROUP);

    expect(resolve(FrontendTranslations::class)->toArray())->toHaveKey(FRONTEND_FIXTURE_GROUP);
});

it('rejects a group that does not exist in any locale', function (string $group): void {
    resolve(FrontendTranslations::class)->use($group)->toArray();
})->with([
    'missing group' => ['fixture/missing'],
    'key of a text' => ['fixture/sample.title'],
])->throws(InvalidArgumentException::class, 'does not exist');
