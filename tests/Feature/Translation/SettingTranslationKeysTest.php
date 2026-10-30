<?php

declare(strict_types=1);

/**
 * The catalogs the React settings pages read: every file of `lang/{locale}/setting/`, the date texts every
 * page receives and Laravel's validation messages, whose Spanish copy the settings forms rely on.
 *
 * @return array<string, array{string}>
 */
function settingTranslationFiles(): array
{
    return [
        ...translationFilesIn('setting'),
        'dates' => ['dates'],
        'validation' => ['validation'],
    ];
}

it('has the same keys in Spanish and English', function (string $file): void {
    $englishKeys = array_keys(translationFileTexts('en', $file));
    $spanishKeys = array_keys(translationFileTexts('es', $file));

    expect(array_values(array_diff($englishKeys, $spanishKeys)))
        ->toBe([], "lang/es/{$file}.php is missing keys")
        ->and(array_values(array_diff($spanishKeys, $englishKeys)))
        ->toBe([], "lang/es/{$file}.php has keys that lang/en/{$file}.php does not");
})->with(settingTranslationFiles());

it('has no empty texts and the same placeholders in both languages', function (string $file): void {
    $spanishTexts = translationFileTexts('es', $file);

    foreach (translationFileTexts('en', $file) as $key => $englishText) {
        $spanishText = $spanishTexts[$key] ?? '';

        expect(trim($englishText))->not->toBe('', "lang/en/{$file}.php: '{$key}' is empty")
            ->and(trim($spanishText))->not->toBe('', "lang/es/{$file}.php: '{$key}' is empty")
            ->and(translationPlaceholders($spanishText))
            ->toBe(translationPlaceholders($englishText), "{$file}.{$key}: the placeholders differ between en and es");
    }
})->with(settingTranslationFiles());

it('has the same settings catalogs in both languages', function (): void {
    $catalogNames = static fn (string $locale): array => array_map(
        basename(...),
        glob(lang_path("{$locale}/setting/*.php")) ?: [],
    );

    expect($catalogNames('es'))->toBe($catalogNames('en'));
});
