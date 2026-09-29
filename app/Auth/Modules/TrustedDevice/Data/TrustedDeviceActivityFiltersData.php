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

    private const array ALLOWED_ACTIONS = [
        TrustedDeviceAction::Created->value,
        TrustedDeviceAction::Renewed->value,
        TrustedDeviceAction::Renamed->value,
        TrustedDeviceAction::Revoked->value,
        TrustedDeviceAction::RevokedAll->value,
        TrustedDeviceAction::Reactivated->value,
    ];

    private const array ALLOWED_SINCE_DAYS = ['7', '30', '90', '180', '365'];

    private const array ALLOWED_PER_PAGE = [5, 10, 15, 25, 50];

    private const int DEFAULT_PER_PAGE = 5;

    /**
     * @param  list<string>|null  $action
     * @param  list<string>|null  $sinceDays
     */
    public function __construct(
        public ?array $action,
        public ?array $sinceDays,
        public string $search,
        public int $perPage,
    ) {}

    /** Create normalized activity filters from a request. */
    public static function fromRequest(Request $request): self
    {
        $perPage = $request->integer('per_page');

        return new self(
            action: self::parseMultiFilter($request->string('action')->toString(), self::ALLOWED_ACTIONS),
            sinceDays: self::parseMultiFilter(
                $request->string('since_days')->toString(),
                self::ALLOWED_SINCE_DAYS,
            ),
            search: trim($request->string('search')->toString()),
            perPage: \in_array($perPage, self::ALLOWED_PER_PAGE, true) ? $perPage : self::DEFAULT_PER_PAGE,
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
