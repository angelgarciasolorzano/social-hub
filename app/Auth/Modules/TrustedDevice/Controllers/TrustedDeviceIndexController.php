<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Controllers;

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Data\TrustedDeviceFiltersData;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Props\TrustedDeviceCurrentProps;
use App\Auth\Modules\TrustedDevice\Resources\TrustedDeviceResource;
use App\Http\Controllers\Controller;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use DeviceDetector\DeviceDetector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

final class TrustedDeviceIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $trustedDeviceCurrentProps = new TrustedDeviceCurrentProps(
            request: $request,
            deviceDetector: resolve(DeviceDetector::class),
            isOptional: false,
        );

        $trustedDeviceFiltersData = TrustedDeviceFiltersData::fromRequest($request);

        [$sortColumn, $sortDirection] = match ($trustedDeviceFiltersData->sort) {
            'most-recent' => ['last_used_at', 'desc'],
            'oldest' => ['last_used_at', 'asc'],
            'name-asc' => ['name', 'asc'],
            'name-desc' => ['name', 'desc'],
            'expiring-soon' => ['expires_at', 'asc'],
        };

        $query = $user->trustedDevices()->orderBy($sortColumn, $sortDirection);

        if ($trustedDeviceFiltersData->search !== '') {
            $search = $trustedDeviceFiltersData->search;

            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('browser', 'like', "%{$search}%")
                    ->orWhere('os_name', 'like', "%{$search}%");
            });
        }

        if ($trustedDeviceFiltersData->status !== null) {
            $query->where(function (Builder $builder) use ($trustedDeviceFiltersData): void {
                foreach ($trustedDeviceFiltersData->status as $status) {
                    if ($status === 'revoked') {
                        $builder->orWhere(fn (Builder $builder): Builder => $builder->onlyTrashed());
                    } elseif ($status === 'active') {
                        $builder->orWhere('expires_at', '>', CarbonImmutable::now());
                    } elseif ($status === 'inactive') {
                        $builder->orWhere('expires_at', '<=', CarbonImmutable::now());
                    }
                }
            });
        }

        if ($trustedDeviceFiltersData->browser !== null) {
            $browsers = $trustedDeviceFiltersData->browser;

            $query->where(function (Builder $builder) use ($browsers): void {
                if (\in_array('otro', $browsers, true)) {
                    $builder->orWhereNotIn('browser', ['Chrome', 'Firefox', 'Safari', 'Edge']);
                }

                foreach ($browsers as $browser) {
                    if ($browser === 'otro') {
                        continue;
                    }

                    $builder->orWhere('browser', 'like', ucfirst($browser).'%');
                }
            });
        }

        if ($trustedDeviceFiltersData->deviceType !== null) {
            $query->where('is_mobile', $trustedDeviceFiltersData->deviceType === 'mobile / tablet');
        }

        if ($trustedDeviceFiltersData->lastAccess !== null) {
            $now = CarbonImmutable::now();

            $query->where(function (Builder $builder) use ($trustedDeviceFiltersData, $now): void {
                foreach ($trustedDeviceFiltersData->lastAccess as $value) {
                    [$since] = match ($value) {
                        '24h' => [$now->subDay()],
                        '7d' => [$now->subDays(7)],
                        '30d' => [$now->subDays(30)],
                    };

                    $builder->orWhere('last_used_at', '>=', $since);
                }
            });
        }

        $perPage = $trustedDeviceFiltersData->perPage;

        $props = [
            'filters' => $trustedDeviceFiltersData->toArray(),
            'trustedDevices' => fn (): LengthAwarePaginator => $query
                ->paginate($perPage)
                ->through(fn (TrustedDevice $trustedDevice): array => new TrustedDeviceResource($trustedDevice)->resolve($request)),
            'stats' => Inertia::defer(fn (): array => $this->buildStats($user), rescue: true),
            'recentActivity' => Inertia::defer(fn (): array => $user->trustedDeviceEvents()
                ->latest('created_at')
                ->limit(3)
                ->get()
                ->map(fn (TrustedDeviceEvent $trustedDeviceEvent): array => [
                    'id' => $trustedDeviceEvent->id,
                    'action' => $trustedDeviceEvent->action->value,
                    'actionLabel' => $trustedDeviceEvent->action->label(),
                    'deviceLabel' => $trustedDeviceEvent->device_label,
                    'ip' => $trustedDeviceEvent->ip,
                    'createdAt' => $trustedDeviceEvent->created_at?->toIso8601String(),
                ])
                ->all(), rescue: true),
            'activityDialog' => Inertia::optional(fn (): array => $this->buildActivity($request)),
        ];

        return Inertia::render('setting/modules/trustedDevice/TrustedDevice', [
            ...$props,
            $trustedDeviceCurrentProps,
        ]);
    }

    /**
     * Build the paginated activity log and sanitize its filters.
     *
     * @return array{
     *     activityLog: LengthAwarePaginator<int, array{
     *         id: int, action: string, actionLabel: string,
     *         deviceLabel: string|null, deviceIsMobile: bool|null, deviceOsName: string|null,
     *         ip: string|null, createdAt: string|null
     *     }>,
     *     activityFilters: array{
     *         action: list<string>|null,
     *         sinceDays: list<string>|null,
     *         search: string
     *     },
     * }
     */
    private function buildActivity(Request $request): array
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $allowedActions = array_map(
            static fn (TrustedDeviceAction $trustedDeviceAction): string => $trustedDeviceAction->value,
            TrustedDeviceAction::cases(),
        );

        $allowedSinceDays = ['7', '30', '90', '180', '365'];

        $actions = $this->parseMultiFilter(
            $request->string('action')->toString(),
            $allowedActions,
        );

        $sinceDays = $this->parseMultiFilter(
            $request->string('since_days')->toString(),
            $allowedSinceDays,
        );

        $search = $request->string('search')->toString();

        $paginator = $user->trustedDeviceEvents()
            ->latest('created_at')
            ->orderByDesc('id')
            ->when($actions !== null, function (Builder $builder) use ($actions): void {
                $builder->whereIn('action', $actions);
            })
            ->when($sinceDays !== null, function (Builder $builder) use ($sinceDays): void {
                if ($sinceDays === null) {
                    return;
                }

                $now = CarbonImmutable::now();

                $builder->where(function (Builder $builder) use ($sinceDays, $now): void {
                    foreach ($sinceDays as $sinceDay) {
                        $builder->orWhere('created_at', '>=', $now->subDays((int) $sinceDay));
                    }
                });
            })
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $builder) use ($search): void {
                    $builder
                        ->where('device_label', 'like', "%{$search}%")
                        ->orWhere('ip', 'like', "%{$search}%")
                        ->orWhere('device_os_name', 'like', "%{$search}%");
                });
            })
            ->paginate(5)
            ->through(fn (TrustedDeviceEvent $trustedDeviceEvent): array => [
                'id' => $trustedDeviceEvent->id,
                'action' => $trustedDeviceEvent->action->value,
                'actionLabel' => $trustedDeviceEvent->action->label(),
                'deviceLabel' => $trustedDeviceEvent->device_label,
                'deviceIsMobile' => $trustedDeviceEvent->device_is_mobile,
                'deviceOsName' => $trustedDeviceEvent->device_os_name,
                'ip' => $trustedDeviceEvent->ip,
                'createdAt' => $trustedDeviceEvent->created_at?->toIso8601String(),
            ]);

        return [
            'activityLog' => $paginator,
            'activityFilters' => [
                'action' => $actions,
                'sinceDays' => $sinceDays,
                'search' => $search,
            ],
        ];
    }

    /**
     * Parse a comma-separated query string value against a whitelist.
     * Returns null when no values match (no filter applied).
     *
     * @template T of string
     *
     * @param  list<T>  $whitelist
     * @return list<T>|null
     */
    private function parseMultiFilter(string $raw, array $whitelist): ?array
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

    /**
     * Aggregate counters shown in the page header stat cards and chart segments.
     *
     * @return array{
     *     total: int,
     *     active: int,
     *     expiringSoon: int,
     *     recentlyAdded: int,
     *     inactive: int,
     *     revoked: int,
     *     byDeviceType: array{desktop: int, mobile: int},
     * }
     */
    private function buildStats(User $user): array
    {
        $now = CarbonImmutable::now();
        $inSevenDays = $now->addDays(7);
        $sevenDaysAgo = $now->subDays(7);

        $byDeviceType = [
            'desktop' => $user->trustedDevices()
                ->whereNull('deleted_at')
                ->where('is_mobile', false)
                ->count(),
            'mobile' => $user->trustedDevices()
                ->whereNull('deleted_at')
                ->where('is_mobile', true)
                ->count(),
        ];

        return [
            'total' => $user->trustedDevices()->count(),
            'active' => $user->trustedDevices()
                ->where('expires_at', '>', $now)
                ->count(),
            'expiringSoon' => $user->trustedDevices()
                ->where('expires_at', '>', $now)
                ->where('expires_at', '<', $inSevenDays)
                ->count(),
            'recentlyAdded' => $user->trustedDevices()
                ->where('created_at', '>', $sevenDaysAgo)
                ->count(),
            'inactive' => $user->trustedDevices()
                ->where('expires_at', '<=', $now)
                ->count(),
            'revoked' => $user->trustedDeviceEvents()
                ->whereIn('action', [TrustedDeviceAction::Revoked, TrustedDeviceAction::RevokedAll])
                ->count(),
            'byDeviceType' => $byDeviceType,
        ];
    }
}
