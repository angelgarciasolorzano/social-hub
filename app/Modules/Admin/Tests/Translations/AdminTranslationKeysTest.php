<?php

declare(strict_types=1);

use App\Modules\ActivityLog\Contracts\ActivityLogEvent;
use App\Modules\ActivityLog\Enums\ActivityLogName;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Lang;

/**
 * The texts of an admin panel translation file, flattened to dotted keys.
 *
 * @return array<string, string>
 */
function adminTranslationTexts(string $locale, string $fileName): array
{
    $path = lang_path("{$locale}/admin/{$fileName}.php");

    expect($path)->toBeFile("lang/{$locale}/admin/{$fileName}.php is missing");

    $translations = require $path;

    expect($translations)->toBeArray("lang/{$locale}/admin/{$fileName}.php must return an array");

    /** @var array<array-key, mixed> $translations */
    return flattenAdminTranslations($translations, "lang/{$locale}/admin/{$fileName}.php");
}

/**
 * @param  array<array-key, mixed>  $translations
 * @return array<string, string>
 */
function flattenAdminTranslations(array $translations, string $file, string $prefix = ''): array
{
    $texts = [];

    foreach ($translations as $key => $value) {
        $dottedKey = $prefix.$key;

        if (\is_array($value)) {
            $texts = [...$texts, ...flattenAdminTranslations($value, $file, $dottedKey.'.')];

            continue;
        }

        expect($value)->toBeString("{$file}: '{$dottedKey}' must be a text");

        /** @var string $value */
        $texts[$dottedKey] = $value;
    }

    return $texts;
}

/**
 * The `:name` placeholders of a text, sorted and lowercased, since Laravel fills `:name`, `:Name` and
 * `:NAME` with the same value (only changing its case).
 *
 * @return list<string>
 */
function adminTranslationPlaceholders(string $text): array
{
    preg_match_all('/:([a-z_]+)/i', $text, $matches);

    $placeholders = array_values(array_unique(array_map(strtolower(...), $matches[1])));
    sort($placeholders);

    return $placeholders;
}

/**
 * Every admin panel translation file, by name, read from the English folder. Pest builds datasets
 * before the application boots, so the path is resolved from this file instead of `lang_path()`.
 *
 * @return array<string, array{string}>
 */
function adminTranslationFiles(): array
{
    $files = [];

    $paths = glob(dirname(__DIR__, 5).'/lang/en/admin/*.php');

    foreach ($paths === false ? [] : $paths as $path) {
        $fileName = basename($path, '.php');
        $files[$fileName] = [$fileName];
    }

    return $files;
}

it('has the same keys in Spanish and English', function (string $fileName): void {
    $englishKeys = array_keys(adminTranslationTexts('en', $fileName));
    $spanishKeys = array_keys(adminTranslationTexts('es', $fileName));

    expect(array_values(array_diff($englishKeys, $spanishKeys)))
        ->toBe([], "lang/es/admin/{$fileName}.php is missing keys")
        ->and(array_values(array_diff($spanishKeys, $englishKeys)))
        ->toBe([], "lang/es/admin/{$fileName}.php has keys that lang/en/admin/{$fileName}.php does not");
})->with(adminTranslationFiles());

it('has no empty texts and the same placeholders in both languages', function (string $fileName): void {
    $spanishTexts = adminTranslationTexts('es', $fileName);

    foreach (adminTranslationTexts('en', $fileName) as $key => $englishText) {
        $spanishText = $spanishTexts[$key] ?? '';

        expect(trim($englishText))->not->toBe('', "lang/en/admin/{$fileName}.php: '{$key}' is empty")
            ->and(trim($spanishText))->not->toBe('', "lang/es/admin/{$fileName}.php: '{$key}' is empty")
            ->and(adminTranslationPlaceholders($spanishText))
            ->toBe(adminTranslationPlaceholders($englishText), "admin/{$fileName}.{$key}: the placeholders differ between en and es");
    }
})->with(adminTranslationFiles());

it('names every cataloged activity event and log in both languages', function (string $locale): void {
    $missingKeys = [];

    foreach (ActivityLogEvent::ENUMS as $enumClass) {
        foreach ($enumClass::cases() as $activityLogEvent) {
            $missingKeys[] = 'events.'.$activityLogEvent->eventName();
        }
    }

    foreach (ActivityLogName::cases() as $activityLogName) {
        $missingKeys[] = 'logs.'.$activityLogName->value;
    }

    foreach (array_keys(Relation::morphMap()) as $morphType) {
        $missingKeys[] = 'parties.types.'.$morphType;
    }

    $missingKeys = array_values(array_filter(
        $missingKeys,
        static fn (string $key): bool => ! Lang::has("admin/activity-log.{$key}", $locale, false),
    ));

    expect($missingKeys)->toBe([], "lang/{$locale}/admin/activity-log.php is missing keys");
})->with(['en', 'es']);
