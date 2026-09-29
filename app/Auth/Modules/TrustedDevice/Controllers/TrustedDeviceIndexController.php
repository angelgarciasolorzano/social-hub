<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Controllers;

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Data\TrustedDeviceActivityFiltersData;
use App\Auth\Modules\TrustedDevice\Data\TrustedDeviceFiltersData;
use App\Auth\Modules\TrustedDevice\Props\TrustedDeviceCurrentProps;
use App\Auth\Modules\TrustedDevice\Resources\TrustedDeviceEventResource;
use App\Auth\Modules\TrustedDevice\Resources\TrustedDeviceResource;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceDashboardCache;
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
    public function __construct(private readonly TrustedDeviceDashboardCache $trustedDeviceDashboardCache) {}

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

        $query = $user->trustedDevices()
            ->withTrashed()
            ->orderBy($sortColumn, $sortDirection);

        if ($trustedDeviceFiltersData->search !== '') {
            $search = $trustedDeviceFiltersData->search;

            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('browser', 'like', "%{$search}%")
                    ->orWhere('os_name', 'like', "%{$search}%");
            });
        }

        if ($trustedDeviceFiltersData->status !== null) {
            $now = CarbonImmutable::now();

            $query->where(function (Builder $builder) use ($trustedDeviceFiltersData, $now): void {
                foreach ($trustedDeviceFiltersData->status as $status) {
                    if ($status === 'revoked') {
                        $builder->orWhere(fn (Builder $builder): Builder => $builder->onlyTrashed());
                    } elseif ($status === 'active') {
                        $builder->orWhere(fn (Builder $builder): Builder => $builder
                            ->whereNull('deleted_at')
                            ->where('expires_at', '>', $now));
                    } elseif ($status === 'inactive') {
                        $builder->orWhere(fn (Builder $builder): Builder => $builder
                            ->whereNull('deleted_at')
                            ->where('expires_at', '<=', $now));
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
            'trustedDevices' => Inertia::defer(
                fn (): LengthAwarePaginator => $query
                    ->paginate($perPage)
                    ->withQueryString()
                    ->through(fn (TrustedDevice $trustedDevice): array => new TrustedDeviceResource($trustedDevice)->resolve($request)),
                rescue: true,
            ),
            'stats' => Inertia::defer(fn (): array => $this->trustedDeviceDashboardCache->stats($user)->toArray(), rescue: true),
            'recentActivity' => Inertia::defer(fn (): array => $this->trustedDeviceDashboardCache->recentActivity($user, $request), rescue: true),
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

        $trustedDeviceActivityFiltersData = TrustedDeviceActivityFiltersData::fromRequest($request);

        $paginator = $user->trustedDeviceEvents()
            ->latest('created_at')
            ->orderByDesc('id')
            ->when($trustedDeviceActivityFiltersData->action !== null, function (Builder $builder) use ($trustedDeviceActivityFiltersData): void {
                $builder->whereIn('action', $trustedDeviceActivityFiltersData->action);
            })
            ->when($trustedDeviceActivityFiltersData->sinceDays !== null, function (Builder $builder) use ($trustedDeviceActivityFiltersData): void {
                if ($trustedDeviceActivityFiltersData->sinceDays === null) {
                    return;
                }

                $now = CarbonImmutable::now();

                $builder->where(function (Builder $builder) use ($trustedDeviceActivityFiltersData, $now): void {
                    foreach ($trustedDeviceActivityFiltersData->sinceDays as $sinceDay) {
                        $builder->orWhere('created_at', '>=', $now->subDays((int) $sinceDay));
                    }
                });
            })
            ->when($trustedDeviceActivityFiltersData->search !== '', function (Builder $builder) use ($trustedDeviceActivityFiltersData): void {
                $builder->where(function (Builder $builder) use ($trustedDeviceActivityFiltersData): void {
                    $builder
                        ->where('device_label', 'like', "%{$trustedDeviceActivityFiltersData->search}%")
                        ->orWhere('ip', 'like', "%{$trustedDeviceActivityFiltersData->search}%")
                        ->orWhere('device_os_name', 'like', "%{$trustedDeviceActivityFiltersData->search}%");
                });
            })
            ->paginate($trustedDeviceActivityFiltersData->perPage)
            ->through(fn (TrustedDeviceEvent $trustedDeviceEvent): array => new TrustedDeviceEventResource($trustedDeviceEvent)
                ->toArray($request));

        return [
            'activityLog' => $paginator,
            'activityFilters' => $trustedDeviceActivityFiltersData->toArray(),
        ];
    }
}
