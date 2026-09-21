<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Data;

use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

final class TrustedDeviceActivityFiltersData extends Data
{
    private const array ALLOWED_SINCE_DAYS = ['7', '30', '90', '180', '365'];

    /**
     * @param  list<string>|null  $action
     * @param  list<string>|null  $sinceDays
     */
    public function __construct(
        public ?array $action,
        public ?array $sinceDays,
        public string $search,
    ) {}

    /** Create normalized activity filters from a request. */
    public static function fromRequest(Request $request): self
    {
        $allowedActions = array_map(
            static fn (TrustedDeviceAction $trustedDeviceAction): string => $trustedDeviceAction->value,
            TrustedDeviceAction::cases(),
        );

        return new self(
            action: self::parseMultiFilter($request->string('action')->toString(), $allowedActions),
            sinceDays: self::parseMultiFilter(
                $request->string('since_days')->toString(),
                self::ALLOWED_SINCE_DAYS,
            ),
            search: $request->string('search')->toString(),
        );
    }

    /**
     * @return array{action: list<string>|null, sinceDays: list<string>|null, search: string}
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'sinceDays' => $this->sinceDays,
            'search' => $this->search,
        ];
    }

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
