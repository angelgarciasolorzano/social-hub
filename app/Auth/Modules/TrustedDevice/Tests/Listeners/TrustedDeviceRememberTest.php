<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceDashboardCache;
use Illuminate\Support\Facades\Cache;

it('creates a trusted device and records a Created event when remember_device is checked', function (): void {
    $user = createUserWithTwoFactor();
    $otherUser = createUser();
    createTrustedDevice($otherUser);
    $trustedDeviceEvent = createTrustedDeviceEvent($otherUser);

    $trustedDeviceDashboardCache = resolve(TrustedDeviceDashboardCache::class);

    $userStatsCacheKey = 'trusted-device:dashboard:'.$user->id.':stats';
    $userActivityCacheKey = 'trusted-device:dashboard:'.$user->id.':recent-activity';
    $otherUserStatsCacheKey = 'trusted-device:dashboard:'.$otherUser->id.':stats';
    $otherUserActivityCacheKey = 'trusted-device:dashboard:'.$otherUser->id.':recent-activity';

    Cache::forget($userStatsCacheKey);
    Cache::forget($userActivityCacheKey);
    Cache::forget($otherUserStatsCacheKey);
    Cache::forget($otherUserActivityCacheKey);

    $trustedDeviceDashboardStatsData = $trustedDeviceDashboardCache->stats($user);
    $userActivityBefore = $trustedDeviceDashboardCache->recentActivity($user, request());
    $otherUsersStatsBefore = $trustedDeviceDashboardCache->stats($otherUser)->toArray();
    $otherUsersActivityBefore = $trustedDeviceDashboardCache->recentActivity($otherUser, request());

    $this->withSession(['login.id' => $user->id])
        ->withHeader('User-Agent', chromeWindowsUserAgent())
        ->post(route('two-factor.login.store'), [
            'code' => validOtpFor($user),
            'remember_device' => true,
        ])
        ->assertRedirect();

    $trustedDevice = TrustedDevice::query()->where('user_id', $user->id)->sole();

    expect($trustedDevice->user_agent)->toBe(chromeWindowsUserAgent())
        ->and($trustedDevice->isActive())->toBeTrue()
        ->and(TrustedDeviceEvent::query()
            ->where('user_id', $user->id)
            ->where('action', TrustedDeviceAction::Created)
            ->exists())->toBeTrue()
        ->and($trustedDeviceDashboardStatsData->total)
        ->toBe(0)
        ->and($userActivityBefore)
        ->toBeEmpty()
        ->and(Cache::has($userStatsCacheKey))
        ->toBeFalse()
        ->and(Cache::has($userActivityCacheKey))
        ->toBeFalse()
        ->and(Cache::has($otherUserStatsCacheKey))
        ->toBeTrue()
        ->and(Cache::has($otherUserActivityCacheKey))
        ->toBeTrue()
        ->and($trustedDeviceDashboardCache->stats($user)->total)
        ->toBe(1)
        ->and(array_column($trustedDeviceDashboardCache->recentActivity($user, request()), 'action'))
        ->toContain(TrustedDeviceAction::Created->value)
        ->and($trustedDeviceDashboardCache->stats($otherUser)
            ->toArray())
        ->toBe($otherUsersStatsBefore)
        ->and($trustedDeviceDashboardCache->recentActivity($otherUser, request()))
        ->toBe($otherUsersActivityBefore)
        ->and(array_column($otherUsersActivityBefore, 'id'))
        ->toBe([$trustedDeviceEvent->id]);
});

it('does not create a trusted device when remember_device is not checked', function (): void {
    $user = createUserWithTwoFactor();
    $otherUser = createUser();
    createTrustedDevice($otherUser);
    createTrustedDeviceEvent($otherUser);

    $trustedDeviceDashboardCache = resolve(TrustedDeviceDashboardCache::class);

    $userStatsCacheKey = 'trusted-device:dashboard:'.$user->id.':stats';
    $userActivityCacheKey = 'trusted-device:dashboard:'.$user->id.':recent-activity';
    $otherUserStatsCacheKey = 'trusted-device:dashboard:'.$otherUser->id.':stats';
    $otherUserActivityCacheKey = 'trusted-device:dashboard:'.$otherUser->id.':recent-activity';

    Cache::forget($userStatsCacheKey);
    Cache::forget($userActivityCacheKey);
    Cache::forget($otherUserStatsCacheKey);
    Cache::forget($otherUserActivityCacheKey);

    $userStatsBefore = $trustedDeviceDashboardCache->stats($user)->toArray();
    $userActivityBefore = $trustedDeviceDashboardCache->recentActivity($user, request());
    $otherUsersStatsBefore = $trustedDeviceDashboardCache->stats($otherUser)->toArray();
    $otherUsersActivityBefore = $trustedDeviceDashboardCache->recentActivity($otherUser, request());

    $this->withSession(['login.id' => $user->id])
        ->post(route('two-factor.login.store'), [
            'code' => validOtpFor($user),
        ])
        ->assertRedirect();

    expect(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and(TrustedDeviceEvent::query()->where('user_id', $user->id)
            ->count())
        ->toBe(0)
        ->and(Cache::has($userStatsCacheKey))
        ->toBeTrue()
        ->and(Cache::has($userActivityCacheKey))
        ->toBeTrue()
        ->and(Cache::has($otherUserStatsCacheKey))
        ->toBeTrue()
        ->and(Cache::has($otherUserActivityCacheKey))
        ->toBeTrue()
        ->and($trustedDeviceDashboardCache->stats($user)
            ->toArray())
        ->toBe($userStatsBefore)
        ->and($trustedDeviceDashboardCache->recentActivity($user, request()))
        ->toBe($userActivityBefore)
        ->and($trustedDeviceDashboardCache->stats($otherUser)
            ->toArray())
        ->toBe($otherUsersStatsBefore)
        ->and($trustedDeviceDashboardCache->recentActivity($otherUser, request()))
        ->toBe($otherUsersActivityBefore);
});
