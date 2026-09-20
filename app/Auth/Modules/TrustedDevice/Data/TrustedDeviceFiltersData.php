<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Data;

use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

final class TrustedDeviceFiltersData extends Data
{
    private const array ALLOWED_STATUS = ['active', 'inactive', 'revoked'];

    private const array ALLOWED_BROWSERS = ['chrome', 'firefox', 'safari', 'edge', 'otro'];

    private const array ALLOWED_DEVICE_TYPES = ['desktop / laptop', 'mobile / tablet'];

    private const array ALLOWED_LAST_ACCESS = ['24h', '7d', '30d'];

    private const array ALLOWED_SORTS = ['most-recent', 'oldest', 'name-asc', 'name-desc', 'expiring-soon'];

    private const array ALLOWED_PER_PAGE = [5, 10, 15, 25, 50];

    /**
     * @param  list<'active'|'inactive'|'revoked'>|null  $status
     * @param  list<'chrome'|'firefox'|'safari'|'edge'|'otro'>|null  $browser
     * @param  'desktop / laptop'|'mobile / tablet'|null  $deviceType
     * @param  list<'24h'|'7d'|'30d'>|null  $lastAccess
     * @param  'most-recent'|'oldest'|'name-asc'|'name-desc'|'expiring-soon'  $sort
     */
    public function __construct(
        public string $search,
        public ?array $status,
        public ?array $browser,
        public ?string $deviceType,
        public ?array $lastAccess,
        public string $sort,
        public int $perPage,
    ) {}

    /**
     * Create normalized filters from a request.
     */
    public static function fromRequest(Request $request): self
    {
        $deviceType = $request->string('device_type')->toString();
        $rawSort = $request->query('sort');
        $perPage = $request->integer('per_page');

        return new self(
            search: trim($request->string('search')->toString()),
            status: self::parseMultiFilter(
                $request->string('status')->toString(),
                self::ALLOWED_STATUS,
            ),
            browser: self::parseMultiFilter(
                $request->string('browser')->toString(),
                self::ALLOWED_BROWSERS,
            ),
            deviceType: \in_array($deviceType, self::ALLOWED_DEVICE_TYPES, true) ? $deviceType : null,
            lastAccess: self::parseMultiFilter(
                $request->string('last_access')->toString(),
                self::ALLOWED_LAST_ACCESS,
            ),
            sort: \is_string($rawSort) && \in_array($rawSort, self::ALLOWED_SORTS, true)
                ? $rawSort
                : 'most-recent',
            perPage: \in_array($perPage, self::ALLOWED_PER_PAGE, true) ? $perPage : 15,
        );
    }

    /**
     * Parse a comma-separated value against a whitelist.
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
