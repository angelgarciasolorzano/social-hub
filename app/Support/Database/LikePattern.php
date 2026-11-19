<?php

declare(strict_types=1);

namespace App\Support\Database;

/**
 * Builds `LIKE` patterns from user input, so the characters with a meaning in `LIKE` match
 * themselves instead of acting as wildcards.
 */
final class LikePattern
{
    /**
     * A pattern that matches the values starting with the term: `%`, `_` and `\` (MySQL's default
     * escape character, escaped first so the other escapes are not doubled) stay literal.
     */
    public static function startsWith(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
    }
}
