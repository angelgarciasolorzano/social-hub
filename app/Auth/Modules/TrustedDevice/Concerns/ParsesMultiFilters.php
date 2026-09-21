<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Concerns;

trait ParsesMultiFilters
{
    /**
     * Parse a comma-separated value against a whitelist.
     * Returns null when no values match.
     *
     * @template T of string
     *
     * @param  list<T>  $whitelist
     * @return list<T>|null
     */
    private static function parseMultiFilter(string $raw, array $whitelist): ?array
    {
        $candidates = array_filter(
            array_map(trim(...), explode(',', $raw)),
            static fn (string $candidate): bool => $candidate !== '',
        );

        $valid = array_values(array_filter(
            $candidates,
            static fn (string $candidate): bool => \in_array($candidate, $whitelist, true),
        ));

        return $valid === [] ? null : $valid;
    }
}
