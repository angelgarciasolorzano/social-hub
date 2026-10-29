<?php

declare(strict_types=1);

/**
 * Filament methods that put text on screen; a literal passed to one of them is a text nobody translated.
 */
const ADMIN_TEXT_METHODS = [
    'alt',
    'description',
    'emptyStateDescription',
    'emptyStateHeading',
    'failureNotificationTitle',
    'falseLabel',
    'heading',
    'helperText',
    'label',
    'modalDescription',
    'modalHeading',
    'modalSubmitActionLabel',
    'placeholder',
    'successNotificationTitle',
    'title',
    'tooltip',
    'trueLabel',
];

/**
 * Admin panel files already translated, relative to the project root.
 */
const ADMIN_TRANSLATED_PATHS = [
    'app/Modules/Admin/Filament/Widgets',
    'app/Modules/Admin/Filament/Resources/Users',
    'app/Modules/Admin/Filament/Pages/Backups',
    'app/Modules/Admin/Filament/Resources/ActivityLogs/Tables/ActivityLogsTable.php',
    'app/Modules/Admin/Filament/Concerns/DescribesActivityLogParties.php',
    'app/Modules/Admin/UI/Views/filament/widgets',
    'app/Modules/Admin/UI/Views/filament/backups',
];

/**
 * The literal texts a PHP source passes to Filament's text methods, to `Stat::make()` or
 * `Section::make()`, or declares in a text property such as `$heading`.
 *
 * @return list<string>
 */
function hardcodedTextsInPhp(string $source): array
{
    $textMethods = implode('|', ADMIN_TEXT_METHODS);
    $patterns = [
        "/->(?:{$textMethods})\\(\\s*(['\"])(.+?)\\1/",
        "/(?:Stat|Section)::make\\(\\s*(['\"])(.+?)\\1/",
        "/protected\\s+(?:static\\s+)?\\??string\\s+\\$(?:title|heading|description|navigationLabel|navigationGroup|emptyStateHeading|emptyStateDescription)\\s*=\\s*(['\"])(.+?)\\1/",
    ];

    $texts = [];

    foreach ($patterns as $pattern) {
        preg_match_all($pattern, $source, $matches);

        foreach ($matches[2] as $text) {
            if (preg_match('/[A-Za-z]{2,}/', $text) === 1) {
                $texts[] = $text;
            }
        }
    }

    return $texts;
}

/**
 * The texts a Blade view writes outside `{{ }}`, and the literal `aria-label`s it declares.
 *
 * @return list<string>
 */
function hardcodedTextsInBlade(string $source): array
{
    $markup = preg_replace(['/<\?php.*?\?>/s', '/\{\{.*?\}\}/s', '/\{!!.*?!!\}/s', '/^\s*@.*$/m'], '', $source) ?? '';

    preg_match_all('/>([^<>]*)</', $markup, $textNodes);
    preg_match_all('/aria-label="([^"{]+)"/', $markup, $ariaLabels);

    $texts = [];

    foreach ([...$textNodes[1], ...$ariaLabels[1]] as $text) {
        $text = trim($text);

        if (preg_match('/[A-Za-z]{2,}/', $text) === 1) {
            $texts[] = $text;
        }
    }

    return $texts;
}

/**
 * Every PHP and Blade file under the translated admin paths.
 *
 * @return list<string>
 */
function adminTranslatedFiles(): array
{
    $files = [];

    foreach (ADMIN_TRANSLATED_PATHS as $path) {
        $absolutePath = base_path($path);

        if (is_file($absolutePath)) {
            $files[] = $absolutePath;

            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }

    sort($files);

    return $files;
}

it('finds no hard-coded text in the translated admin sections', function (): void {
    $hardcodedTexts = [];

    foreach (adminTranslatedFiles() as $file) {
        $source = (string) file_get_contents($file);
        $texts = str_ends_with($file, '.blade.php') ? hardcodedTextsInBlade($source) : hardcodedTextsInPhp($source);

        foreach ($texts as $text) {
            $hardcodedTexts[] = str_replace(base_path().'/', '', $file).": {$text}";
        }
    }

    expect(adminTranslatedFiles())->not->toBeEmpty()
        ->and($hardcodedTexts)->toBe([], 'Use TranslatedText::of() in PHP or __() in Blade with a key in lang/{en,es}/admin/');
});

it('detects literal texts passed to Filament', function (string $source, array $expectedTexts): void {
    expect(hardcodedTextsInPhp($source))->toBe($expectedTexts);
})->with([
    'a label' => ["->label('Status')", ['Status']],
    'a stat' => ["Stat::make('Total accounts', \$count)", ['Total accounts']],
    'a heading property' => ["protected ?string \$heading = 'Account overview';", ['Account overview']],
    'a translated label' => ["->label(TranslatedText::of('admin/users.table.status'))", []],
    'an icon and a color' => ["->icon(Heroicon::OutlinedUser)->color('success')", []],
]);

it('detects literal texts in Blade views', function (string $source, array $expectedTexts): void {
    expect(hardcodedTextsInBlade($source))->toBe($expectedTexts);
})->with([
    'a slot' => ['<x-slot name="heading">Quick access</x-slot>', ['Quick access']],
    'an aria label' => ['<nav aria-label="Dashboard quick links">', ['Dashboard quick links']],
    'a translated slot' => ["<x-slot name=\"heading\">{{ __('admin/dashboard.quick_links.heading') }}</x-slot>", []],
    'a translated aria label' => ["<nav aria-label=\"{{ __('admin/dashboard.quick_links.aria_label') }}\">", []],
]);
