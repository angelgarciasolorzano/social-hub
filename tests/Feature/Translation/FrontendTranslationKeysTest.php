<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * A quoted literal shaped like a key of the catalogs React reads: `setting/<group>.<key>` or `dates.<key>`.
 */
const FRONTEND_TRANSLATION_KEY_PATTERN = '/["\'`]((?:setting\/[a-z-]+|dates)\.[A-Za-z0-9_.]+)["\'`]/';

/**
 * Every translation key literal of the React sources, with the file and line it appears on. TypeScript does
 * not type the keys, so this is what catches a key misspelled in a `.tsx` or a `data/*.ts` file.
 *
 * @return list<array{key: string, location: string}>
 */
function frontendTranslationKeyUsages(): array
{
    $usages = [];

    foreach (File::allFiles(resource_path('js')) as $file) {
        $relativePath = $file->getRelativePathname();

        if (! in_array($file->getExtension(), ['ts', 'tsx'], true) || str_starts_with($relativePath, 'shared/wayfinder/')) {
            continue;
        }

        foreach (file($file->getPathname()) ?: [] as $lineIndex => $line) {
            preg_match_all(FRONTEND_TRANSLATION_KEY_PATTERN, $line, $matches);

            foreach ($matches[1] as $key) {
                $usages[] = ['key' => $key, 'location' => 'resources/js/'.$relativePath.':'.($lineIndex + 1)];
            }
        }
    }

    return $usages;
}

/**
 * Whether the key names a text, not a group of texts, in the given language.
 */
function isFrontendTranslationText(string $key, string $locale): bool
{
    static $textsByCatalog = [];

    [$group, $path] = explode('.', $key, 2);

    $textsByCatalog[$locale][$group] ??= translationFileTexts($locale, $group);

    return isset($textsByCatalog[$locale][$group][$path]);
}

it('finds the translation keys the React settings pages use', function (): void {
    $usedKeys = array_unique(array_column(frontendTranslationKeyUsages(), 'key'));

    expect(count($usedKeys))->toBeGreaterThan(300)
        ->and($usedKeys)->toContain('setting/common.actions.cancel', 'dates.formats.long');
});

it('uses only keys that name a text in both languages', function (string $locale): void {
    $missingKeys = [];

    foreach (frontendTranslationKeyUsages() as $usage) {
        if (! isFrontendTranslationText($usage['key'], $locale)) {
            $missingKeys[] = "{$usage['location']} {$usage['key']}";
        }
    }

    expect($missingKeys)->toBe([], "These keys are not texts of lang/{$locale}/");
})->with(['en', 'es']);
