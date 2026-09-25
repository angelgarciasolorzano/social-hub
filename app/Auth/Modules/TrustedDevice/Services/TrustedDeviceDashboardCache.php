<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Services;

use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Data\TrustedDeviceDashboardStatsData;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Resources\TrustedDeviceEventResource;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class TrustedDeviceDashboardCache
{
    private const int STATS_TTL_SECONDS = 60;

    private const int RECENT_ACTIVITY_TTL_SECONDS = 30;

    private const string STATS_CACHE_KEY = 'trusted-device:dashboard:%s:stats';

    private const string RECENT_ACTIVITY_CACHE_KEY = 'trusted-device:dashboard:%s:recent-activity';

    public function stats(User $user): TrustedDeviceDashboardStatsData
    {
        $cachedStats = Cache::remember(
            $this->cacheKey(self::STATS_CACHE_KEY, $user->id),
            self::STATS_TTL_SECONDS,
            fn (): array => $this->buildStats($user)->toArray(),
        );

        return TrustedDeviceDashboardStatsData::from($cachedStats);
    }

    /**
     * Return the latest activity rows as resource arrays.
     *
     * @return list<array{
     *     id: int,
     *     action: string,
     *     actionLabel: string,
     *     deviceLabel: string|null,
     *     deviceIsMobile: bool|null,
     *     deviceOsName: string|null,
     *     ip: string|null,
     *     createdAt: string|null,
     * }>
     */
    public function recentActivity(User $user, Request $request): array
    {
        /**
         * @var list<array{
         *     id: int,
         *     action: string,
         *     actionLabel: string,
         *     deviceLabel: string|null,
         *     deviceIsMobile: bool|null,
         *     deviceOsName: string|null,
         *     ip: string|null,
         *     createdAt: string|null,
         * }> $recentActivity
         */
        $recentActivity = Cache::remember(
            $this->cacheKey(self::RECENT_ACTIVITY_CACHE_KEY, $user->id),
            self::RECENT_ACTIVITY_TTL_SECONDS,
            fn (): array => $user->trustedDeviceEvents()
                ->latest('created_at')
                ->limit(3)
                ->get()
                ->map(fn (TrustedDeviceEvent $trustedDeviceEvent): array => new TrustedDeviceEventResource($trustedDeviceEvent)
                    ->resolve($request))
                ->all(),
        );

        return $recentActivity;
    }

    private function buildStats(User $user): TrustedDeviceDashboardStatsData
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

        return new TrustedDeviceDashboardStatsData(
            total: $user->trustedDevices()->count(),
            active: $user->trustedDevices()
                ->where('expires_at', '>', $now)
                ->count(),
            expiringSoon: $user->trustedDevices()
                ->where('expires_at', '>', $now)
                ->where('expires_at', '<', $inSevenDays)
                ->count(),
            recentlyAdded: $user->trustedDevices()
                ->where('created_at', '>', $sevenDaysAgo)
                ->count(),
            inactive: $user->trustedDevices()
                ->where('expires_at', '<=', $now)
                ->count(),
            revoked: $user->trustedDeviceEvents()
                ->whereIn('action', [TrustedDeviceAction::Revoked, TrustedDeviceAction::RevokedAll])
                ->count(),
            byDeviceType: $byDeviceType,
        );
    }

    private function cacheKey(string $cacheKeyTemplate, int $userId): string
    {
        return \sprintf($cacheKeyTemplate, $userId);
    }
}
