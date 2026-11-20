<?php

declare(strict_types=1);

use App\Modules\Notification\Enums\NotificationType;
use App\User\Enums\PreferredLocale;

it('has the same keys in Spanish and English', function (string $file): void {
    $englishKeys = array_keys(translationFileTexts('en', $file));
    $spanishKeys = array_keys(translationFileTexts('es', $file));

    expect(array_values(array_diff($englishKeys, $spanishKeys)))
        ->toBe([], "lang/es/{$file}.php is missing keys")
        ->and(array_values(array_diff($spanishKeys, $englishKeys)))
        ->toBe([], "lang/es/{$file}.php has keys that lang/en/{$file}.php does not");
})->with(translationFilesIn('notification'));

it('has no empty texts and the same placeholders in both languages', function (string $file): void {
    $spanishTexts = translationFileTexts('es', $file);

    foreach (translationFileTexts('en', $file) as $key => $englishText) {
        $spanishText = $spanishTexts[$key] ?? '';

        expect(trim($englishText))->not->toBe('', "lang/en/{$file}.php: '{$key}' is empty")
            ->and(trim($spanishText))->not->toBe('', "lang/es/{$file}.php: '{$key}' is empty")
            ->and(translationPlaceholders($spanishText))
            ->toBe(translationPlaceholders($englishText), "{$file}.{$key}: the placeholders differ between en and es");
    }
})->with(translationFilesIn('notification'));

it('has the same notification catalogs in both languages', function (): void {
    $catalogNames = static function (string $locale): array {
        $paths = glob(lang_path("{$locale}/notification/*.php"));

        return array_map(basename(...), $paths === false ? [] : $paths);
    };

    expect($catalogNames('es'))->toBe($catalogNames('en'));
});

it('has a name for every notification type in :dataset', function (PreferredLocale $preferredLocale): void {
    $texts = translationFileTexts($preferredLocale->value, 'notification/center');

    foreach (NotificationType::values() as $notificationType) {
        expect($texts)->toHaveKey("types.{$notificationType}", message: "notification/center.types.{$notificationType} is missing");
    }
})->with([
    'English' => [PreferredLocale::ENGLISH],
    'Spanish' => [PreferredLocale::SPANISH],
]);

it('names the actor in the friendship summaries of :dataset', function (PreferredLocale $preferredLocale): void {
    expect(translatedTextIn($preferredLocale, 'notification/center.summaries.friend_request_received'))
        ->toContain(':actor')
        ->and(translatedTextIn($preferredLocale, 'notification/center.summaries.friend_request_accepted'))
        ->toContain(':actor');
})->with([
    'English' => [PreferredLocale::ENGLISH],
    'Spanish' => [PreferredLocale::SPANISH],
]);
