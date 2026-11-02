<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * The React settings folders already translated, relative to the project root. Appearance
 * (`modules/preference`) is not translated yet, so it is left out.
 */
const SETTING_TRANSLATED_PATHS = [
    'resources/js/modules/setting/shared',
    'resources/js/modules/setting/modules/accountSettings',
    'resources/js/modules/setting/modules/twoFactor',
    'resources/js/modules/setting/modules/trustedDevice',
];

/**
 * Literals that may stay in the code, by file relative to the project root. Each one needs a reason.
 */
const SETTING_ALLOWED_LITERALS = [
    // Browser names are proper nouns; the filter values travel in the URL.
    'resources/js/modules/setting/modules/trustedDevice/data/trustedDeviceFilters.ts' => ['Chrome', 'Firefox', 'Safari', 'Edge'],
    // The placeholder shows the format of a recovery code, not a text.
    'resources/js/modules/setting/shared/components/TwoFactorCodeField.tsx' => ['xxxxxxxxxx-xxxxxxxxxx'],
];

/**
 * Texts a translated source still writes by hand, each with its line: JSX text, literal values of the
 * attributes and object properties that hold visible text, and any literal with Spanish characters.
 *
 * @return list<array{line: int, text: string}>
 */
function hardcodedTextsInReact(string $source): array
{
    $code = (string) preg_replace_callback(
        '/\/\*.*?\*\/|(?<![:"\'`])\/\/[^\n]*/s',
        static fn (array $comment): string => str_repeat("\n", substr_count($comment[0], "\n")),
        $source,
    );

    $patterns = [
        '/(?<![=\-])>\s*([^<>{}()=;`"\']*[A-Za-zÁÉÍÓÚáéíóúñÑ][^<>{}()=;`"\']*?)\s*(?=<[\/A-Za-z])/u',
        '/\b(?:title|placeholder|aria-label|aria-valuetext|alt|label|description|ariaLabel)="([^"]*[A-Za-z][^"]*)"/',
        '/\b(?:title|label|description|placeholder|message)\s*:\s*"([^"]*[A-Za-z][^"]*)"/',
        '/["\'`]([^"\'`\n]*[áéíóúñÁÉÍÓÚÑ¿¡][^"\'`\n]*)["\'`]/u',
    ];

    $texts = [];

    foreach ($patterns as $pattern) {
        preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as [$text, $offset]) {
            $text = trim($text);

            if ($text === '' || preg_match('/[A-Za-zÁÉÍÓÚáéíóúñÑ]{2,}/u', $text) !== 1 || str_starts_with($text, 'setting/')) {
                continue;
            }

            $texts[] = ['line' => substr_count(substr($code, 0, $offset), "\n") + 1, 'text' => $text];
        }
    }

    return $texts;
}

/**
 * Every `.ts` and `.tsx` file under the translated settings folders, relative to the project root.
 *
 * @return list<string>
 */
function settingTranslatedFiles(): array
{
    $files = [];

    foreach (SETTING_TRANSLATED_PATHS as $path) {
        foreach (File::allFiles(base_path($path)) as $file) {
            if (in_array($file->getExtension(), ['ts', 'tsx'], true)) {
                $files[] = $path.'/'.$file->getRelativePathname();
            }
        }
    }

    sort($files);

    return $files;
}

it('finds the files of the translated settings pages', function (): void {
    expect(count(settingTranslatedFiles()))->toBeGreaterThan(80);
});

it('writes no visible text by hand in the translated settings pages', function (): void {
    $hardcodedTexts = [];

    foreach (settingTranslatedFiles() as $file) {
        $allowedLiterals = SETTING_ALLOWED_LITERALS[$file] ?? [];

        foreach (hardcodedTextsInReact((string) file_get_contents(base_path($file))) as ['line' => $line, 'text' => $text]) {
            if (! in_array($text, $allowedLiterals, true)) {
                $hardcodedTexts[] = "{$file}:{$line} {$text}";
            }
        }
    }

    expect($hardcodedTexts)->toBe([], 'Write these texts through t() and a key of lang/{en,es}/setting/');
});

it('flags JSX text, text attributes, text properties and Spanish literals', function (string $source): void {
    expect(hardcodedTextsInReact($source))->not->toBeEmpty();
})->with([
    'JSX text' => ["<p>\n  Hola mundo\n</p>"],
    'placeholder' => ['<Input placeholder="Buscar" />'],
    'aria-label' => ['<Button aria-label="Close dialog" />'],
    'data label' => ['const option = { label: "Activo" };'],
    'Spanish literal' => ['toast.error("Inténtalo de nuevo");'],
]);

it('ignores keys, code and comments', function (string $source): void {
    expect(hardcodedTextsInReact($source))
        ->toBeEmpty();
})->with([
    'translated text' => ['<p>{t("setting/common.actions.cancel")}</p>'],
    'key in data' => ['const option = { label: "setting/trusted-devices.status.active" };'],
    'generic types' => ['const [open, setOpen] = useState<boolean>(false);'],
    'arrow function returning a promise' => ['fetchRecoveryCodes: () => Promise<void>;'],
    'comment' => ["// Tú decides\n/** Configuración */"],
    'class names' => ['<div className="flex items-center gap-2" />'],
]);
