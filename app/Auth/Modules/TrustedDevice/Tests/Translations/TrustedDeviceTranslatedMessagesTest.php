<?php

declare(strict_types=1);

use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceOperationResult;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceDashboardCache;
use Illuminate\Http\Request;

/**
 * The text a callback returns in each language, keyed by locale.
 *
 * @param  Closure(): string  $text
 * @return array{en: string, es: string}
 */
function textInEachLanguage(Closure $text): array
{
    app()->setLocale('en');
    $englishText = $text();

    app()->setLocale('es');
    $spanishText = $text();

    return ['en' => $englishText, 'es' => $spanishText];
}

it('gives every operation result a flash text in English and in Spanish', function (TrustedDeviceOperationResult $trustedDeviceOperationResult): void {
    $texts = textInEachLanguage(fn (): string => $trustedDeviceOperationResult->message());

    expect($texts['en'])->not->toStartWith('setting/')
        ->and($texts['es'])->not->toStartWith('setting/')
        ->and($texts['en'])->not->toBe($texts['es']);
})->with(TrustedDeviceOperationResult::cases());

it('gives every device action a name in English and in Spanish', function (TrustedDeviceAction $trustedDeviceAction): void {
    $texts = textInEachLanguage(fn (): string => $trustedDeviceAction->label());

    expect($texts['en'])->not->toStartWith('setting/')
        ->and($texts['es'])->not->toStartWith('setting/')
        ->and($texts['en'])->not->toBe($texts['es']);
})->with(TrustedDeviceAction::cases());

it('keeps the flash payload shape in the active language', function (): void {
    app()->setLocale('es');

    expect(TrustedDeviceOperationResult::AlreadyActive->payload())->toBe([
        'type' => 'error',
        'message' => 'Este dispositivo ya está registrado como de confianza.',
    ]);
});

it('labels the cached recent activity in the language of each request', function (): void {
    $user = createUser();
    createTrustedDeviceEvent($user, ['action' => TrustedDeviceAction::Renamed]);
    $trustedDeviceDashboardCache = resolve(TrustedDeviceDashboardCache::class);

    $labels = textInEachLanguage(
        fn (): string => $trustedDeviceDashboardCache->recentActivity($user, Request::create('/'))[0]['actionLabel'],
    );

    expect($labels)->toBe(['en' => 'Device renamed', 'es' => 'Dispositivo renombrado']);
});
