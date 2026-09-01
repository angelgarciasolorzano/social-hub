<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Controllers;

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Concerns\InfersDeviceMetadata;
use App\Auth\Modules\TrustedDevice\Concerns\MintsTrustedDeviceToken;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Props\CurrentTrustedDeviceProps;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceDestroyAllRequest;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceDestroyRequest;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceStoreRequest;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceUpdateRequest;
use App\Auth\Modules\TrustedDevice\Resources\TrustedDeviceResource;
use App\Http\Controllers\Controller;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use DeviceDetector\DeviceDetector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class TrustedDeviceController extends Controller
{
    use InfersDeviceMetadata;
    use MintsTrustedDeviceToken;

    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $currentTrustedDeviceProps = new CurrentTrustedDeviceProps(
            request: $request,
            deviceDetector: resolve(DeviceDetector::class),
            isOptional: false,
        );

        $filters = $this->extractFilters($request);

        [$sortColumn, $sortDirection] = match ($filters['sort']) {
            'most-recent' => ['last_used_at', 'desc'],
            'oldest' => ['last_used_at', 'asc'],
            'name-asc' => ['name', 'asc'],
            'name-desc' => ['name', 'desc'],
            'expiring-soon' => ['expires_at', 'asc'],
        };

        $query = $user->trustedDevices()->orderBy($sortColumn, $sortDirection);

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('browser', 'like', "%{$search}%")
                    ->orWhere('os_name', 'like', "%{$search}%");
            });
        }

        if ($filters['status'] !== null) {
            $query->where('expires_at', $filters['status'] === 'active' ? '>' : '<=', CarbonImmutable::now());
        }

        if ($filters['browser'] !== null) {
            $browsers = $filters['browser'];

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

        if ($filters['deviceType'] !== null) {
            $query->where('is_mobile', $filters['deviceType'] === 'mobile / tablet');
        }

        if ($filters['lastAccess'] !== null) {
            $now = CarbonImmutable::now();

            $query->where(function (Builder $builder) use ($filters, $now): void {
                foreach ($filters['lastAccess'] as $value) {
                    [$since] = match ($value) {
                        '24h' => [$now->subDay()],
                        '7d' => [$now->subDays(7)],
                        '30d' => [$now->subDays(30)],
                    };

                    $builder->orWhere('last_used_at', '>=', $since);
                }
            });
        }

        $perPage = $filters['perPage'];

        $props = [
            'filters' => $filters,
            'trustedDevices' => $query
                ->paginate($perPage)
                ->through(fn (TrustedDevice $trustedDevice): array => new TrustedDeviceResource($trustedDevice)->resolve($request)),
            'stats' => $this->buildStats($user),
            'recentActivity' => $user->trustedDeviceEvents()
                ->latest('created_at')
                ->limit(3)
                ->with(['device'])
                ->get()
                ->map(fn (TrustedDeviceEvent $trustedDeviceEvent): array => [
                    'id' => $trustedDeviceEvent->id,
                    'action' => $trustedDeviceEvent->action->value,
                    'actionLabel' => $trustedDeviceEvent->action->label(),
                    'deviceId' => $trustedDeviceEvent->trusted_device_id,
                    'deviceLabel' => $trustedDeviceEvent->device?->name,
                    'ip' => $trustedDeviceEvent->ip,
                    'createdAt' => $trustedDeviceEvent->created_at?->toIso8601String(),
                ])
                ->all(),
        ];

        return Inertia::render('setting/modules/trustedDevices/TrustedDevice', [
            ...$props,
            $currentTrustedDeviceProps,
        ]);
    }

    /**
     * Sanitize the filter query string against each whitelist, falling back to safe defaults.
     *
     * @return array{
     *     search: string,
     *     status: 'active'|'inactive'|null,
     *     browser: list<'chrome'|'firefox'|'safari'|'edge'|'otro'>|null,
     *     deviceType: 'desktop / laptop'|'mobile / tablet'|null,
     *     lastAccess: list<'24h'|'7d'|'30d'>|null,
     *     sort: 'most-recent'|'oldest'|'name-asc'|'name-desc'|'expiring-soon',
     *     perPage: int,
     * }
     */
    private function extractFilters(Request $request): array
    {
        $allowedStatus = ['active', 'inactive'];
        $allowedBrowsers = ['chrome', 'firefox', 'safari', 'edge', 'otro'];
        $allowedDeviceTypes = ['desktop / laptop', 'mobile / tablet'];
        $allowedLastAccess = ['24h', '7d', '30d'];
        $allowedSorts = ['most-recent', 'oldest', 'name-asc', 'name-desc', 'expiring-soon'];
        $allowedPerPage = [5, 10, 15, 25, 50];

        $status = $request->string('status')->toString();

        $browser = $request->string('browser')->toString();

        $deviceType = $request->string('device_type')->toString();

        $lastAccess = $request->string('last_access')->toString();

        $sort = $request->query('sort');

        $perPage = $request->integer('per_page');

        return [
            'search' => trim($request->string('search')->toString()),
            'status' => \in_array($status, $allowedStatus, true) ? $status : null,
            'browser' => $this->parseMultiFilter($browser, $allowedBrowsers),
            'deviceType' => \in_array($deviceType, $allowedDeviceTypes, true) ? $deviceType : null,
            'lastAccess' => $this->parseMultiFilter($lastAccess, $allowedLastAccess),
            'sort' => \in_array($sort, $allowedSorts, true) ? $sort : 'most-recent',
            'perPage' => \in_array($perPage, $allowedPerPage, true) ? $perPage : 15,
        ];
    }

    /**
     * Parse a comma-separated query string value against the whitelist.
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
     * }
     */
    private function buildStats(User $user): array
    {
        $now = CarbonImmutable::now();
        $inSevenDays = $now->addDays(7);
        $sevenDaysAgo = $now->subDays(7);

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
                ->whereNotNull('trusted_device_id')
                ->distinct()
                ->count('trusted_device_id'),
        ];
    }

    public function update(TrustedDeviceUpdateRequest $trustedDeviceUpdateRequest, TrustedDevice $trustedDevice): RedirectResponse
    {
        abort_unless($trustedDevice->user_id === $trustedDeviceUpdateRequest->user()?->getKey(), 403);

        $trustedDevice->forceFill([
            'name' => $trustedDeviceUpdateRequest->string('name')->toString(),
        ])->save();

        TrustedDeviceEvent::record(
            trustedDevice: $trustedDevice,
            user: $trustedDeviceUpdateRequest->user(),
            trustedDeviceAction: TrustedDeviceAction::Renamed,
            request: $trustedDeviceUpdateRequest,
        );

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Dispositivo renombrado correctamente.',
        ])->back();
    }

    public function renew(Request $request, TrustedDevice $trustedDevice): RedirectResponse
    {
        abort_unless($trustedDevice->user_id === $request->user()?->getKey(), 403);

        /** @var int $cookieLifetimeMinutes */
        $cookieLifetimeMinutes = config('module.auth.trusted_devices.cookie_lifetime_minutes');

        $trustedDevice->forceFill([
            'expires_at' => CarbonImmutable::now()->addMinutes($cookieLifetimeMinutes),
        ])->save();

        TrustedDeviceEvent::record(
            trustedDevice: $trustedDevice,
            user: $request->user(),
            trustedDeviceAction: TrustedDeviceAction::Renewed,
            request: $request,
        );

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Confianza renovada correctamente.',
        ])->back();
    }

    public function store(TrustedDeviceStoreRequest $trustedDeviceStoreRequest): RedirectResponse
    {
        $user = $trustedDeviceStoreRequest->user();

        abort_unless($user instanceof User, 401);

        /** @var DeviceDetector $deviceDetector */
        $deviceDetector = resolve(DeviceDetector::class);

        $osInfo = $this->inferOsInfo($deviceDetector);

        $userAgent = $trustedDeviceStoreRequest->userAgent();
        $ip = $trustedDeviceStoreRequest->ip();

        /** @var int $cookieLifetimeMinutes */
        $cookieLifetimeMinutes = config('module.auth.trusted_devices.cookie_lifetime_minutes');

        $token = $this->mintToken();

        /** @var TrustedDevice $newDevice */
        $newDevice = DB::transaction(function () use (
            $user,
            $deviceDetector,
            $osInfo,
            $token,
            $userAgent,
            $ip,
            $cookieLifetimeMinutes,
            $trustedDeviceStoreRequest,
        ): TrustedDevice {
            if ($userAgent !== null && $ip !== null) {
                $existingMatch = TrustedDevice::findActiveMatch(
                    $user,
                    $userAgent,
                    $osInfo['name'],
                    $ip,
                    lockForUpdate: true,
                );

                if ($existingMatch instanceof TrustedDevice) {
                    return $existingMatch;
                }
            }

            $created = $user->trustedDevices()->create([
                'name' => $trustedDeviceStoreRequest->string('name')->toString() !== ''
                    ? $trustedDeviceStoreRequest->string('name')->toString()
                    : $this->inferDeviceName($deviceDetector),
                'token_hash' => $token['hash'],
                'user_agent' => $userAgent,
                'browser' => $this->inferBrowser($deviceDetector),
                'os_name' => $osInfo['name'],
                'os_version' => $osInfo['version'],
                'is_mobile' => $this->inferIsMobile($deviceDetector),
                'ip' => $ip,
                'last_used_at' => CarbonImmutable::now(),
                'expires_at' => CarbonImmutable::now()->addMinutes($cookieLifetimeMinutes),
            ]);

            if ($userAgent !== null) {
                TrustedDevice::pruneOlder($user, $userAgent, $osInfo['name'], $ip, $created->id);
            }

            return $created;
        });

        $isFresh = $newDevice->wasRecentlyCreated;

        if (! $isFresh) {
            return Inertia::flash([
                'type' => 'error',
                'message' => 'Este dispositivo ya esta registrado como de confianza.',
            ])->back();
        }

        $this->queueTrustedDeviceCookie($token['token']);

        TrustedDeviceEvent::record(
            trustedDevice: $newDevice,
            user: $user,
            trustedDeviceAction: TrustedDeviceAction::Created,
            request: $trustedDeviceStoreRequest,
        );

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Dispositivo agregado correctamente.',
        ])->back();
    }

    public function destroy(TrustedDeviceDestroyRequest $trustedDeviceDestroyRequest, TrustedDevice $trustedDevice): RedirectResponse
    {
        abort_unless($trustedDevice->user_id === $trustedDeviceDestroyRequest->user()?->getKey(), 403);

        TrustedDeviceEvent::record(
            trustedDevice: $trustedDevice,
            user: $trustedDeviceDestroyRequest->user(),
            trustedDeviceAction: TrustedDeviceAction::Revoked,
            request: $trustedDeviceDestroyRequest,
        );

        $trustedDevice->delete();

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Dispositivo de confianza revocado correctamente.',
        ])->back();
    }

    public function destroyAll(TrustedDeviceDestroyAllRequest $trustedDeviceDestroyAllRequest): RedirectResponse
    {
        $user = $trustedDeviceDestroyAllRequest->user();

        abort_unless($user instanceof User, 401);

        $devices = $user->trustedDevices()->latest('last_used_at')->get();

        foreach ($devices as $device) {
            TrustedDeviceEvent::record(
                trustedDevice: $device,
                user: $user,
                trustedDeviceAction: TrustedDeviceAction::RevokedAll,
                request: $trustedDeviceDestroyAllRequest,
            );
        }

        $user->trustedDevices()->delete();

        return Inertia::flash([
            'type' => 'success',
            'message' => 'Todos los dispositivos de confianza fueron revocados.',
        ])->back();
    }
}
