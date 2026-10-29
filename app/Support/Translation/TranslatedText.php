<?php

declare(strict_types=1);

namespace App\Support\Translation;

use LogicException;

final class TranslatedText
{
    /**
     * The text of a translation key in the current locale, typed as a string. `__()` also returns an
     * array when the key names a group of texts, which is always a mistake where a text is expected.
     * A missing key returns the key itself, as `__()` does.
     *
     * @param  array<string, string|int|float>  $replace
     *
     * @throws LogicException
     */
    public static function of(string $key, array $replace = []): string
    {
        $text = __($key, $replace);

        throw_unless(\is_string($text), LogicException::class, "The translation key [{$key}] is a group of texts, not a text.");

        return $text;
    }
}
