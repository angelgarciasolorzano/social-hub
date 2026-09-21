<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Data;

use App\Auth\Modules\TrustedDevice\Concerns\ParsesMultiFilters;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

final class TrustedDeviceActivityFiltersData extends Data
{
    use ParsesMultiFilters;

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
}
