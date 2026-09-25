<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceDashboardCache;
use Illuminate\Support\Facades\Cache;

it('revokes every trusted device and records a RevokedAll event for each when 2FA is disabled', function (): void {
    $user = createUserWithTwoFactor();
    createTrustedDevice($user, ['name' => 'MacBook']);
    createTrustedDevice($user, ['name' => 'iPhone']);

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

    $trustedDeviceDashboardCache->stats($user);
    $trustedDeviceDashboardCache->recentActivity($user, request());

    $otherUsersStatsBefore = $trustedDeviceDashboardCache->stats($otherUser)->toArray();
    $otherUsersActivityBefore = $trustedDeviceDashboardCache->recentActivity($otherUser, request());

    $this->actingAs($user)
        ->delete(route('setting.security.two-factor-authentication.destroy'), [
            'password' => 'password',
            'code' => validOtpFor($user),
        ])
        ->assertRedirect();

    expect(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and(TrustedDevice::withTrashed()->where('user_id', $user->id)->count())->toBe(2)
        ->and(TrustedDeviceEvent::query()
            ->where('user_id', $user->id)
            ->where('action', TrustedDeviceAction::RevokedAll)
            ->count())->toBe(2)
        ->and(TrustedDeviceEvent::query()
            ->where('user_id', $user->id)
            ->where('action', TrustedDeviceAction::RevokedAll)
            ->where('device_label', 'MacBook')
            ->exists())->toBeTrue()
        ->and(TrustedDeviceEvent::query()
            ->where('user_id', $user->id)
            ->where('action', TrustedDeviceAction::RevokedAll)
            ->where('device_label', 'iPhone')
            ->exists())->toBeTrue()
        ->and(Cache::has($userStatsCacheKey))->toBeFalse()
        ->and(Cache::has($userActivityCacheKey))->toBeFalse()
        ->and(Cache::has($otherUserStatsCacheKey))->toBeTrue()
        ->and(Cache::has($otherUserActivityCacheKey))->toBeTrue()
        ->and($trustedDeviceDashboardCache->stats($user)->total)->toBe(0)
        ->and($trustedDeviceDashboardCache->stats($user)->revoked)->toBe(2)
        ->and(array_column($trustedDeviceDashboardCache->recentActivity($user, request()), 'action'))
        ->toBe([TrustedDeviceAction::RevokedAll->value, TrustedDeviceAction::RevokedAll->value])
        ->and($trustedDeviceDashboardCache->stats($otherUser)->toArray())->toBe($otherUsersStatsBefore)
        ->and($trustedDeviceDashboardCache->recentActivity($otherUser, request()))
        ->toBe($otherUsersActivityBefore);
});

it("invalidates only the current user's cache when 2FA is disabled without devices", function (): void {
    $user = createUserWithTwoFactor();

    $otherUser = createUser();
    $trustedDevice = createTrustedDevice($otherUser);
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

    $trustedDeviceDashboardCache->stats($user);
    $trustedDeviceDashboardCache->recentActivity($user, request());

    $otherUsersStatsBefore = $trustedDeviceDashboardCache->stats($otherUser)->toArray();
    $otherUsersActivityBefore = $trustedDeviceDashboardCache->recentActivity($otherUser, request());

    $this->actingAs($user)
        ->delete(route('setting.security.two-factor-authentication.destroy'), [
            'password' => 'password',
            'code' => validOtpFor($user),
        ]);

    expect($trustedDevice->fresh()?->deleted_at)->toBeNull()
        ->and(Cache::has($userStatsCacheKey))->toBeFalse()
        ->and(Cache::has($userActivityCacheKey))->toBeFalse()
        ->and(Cache::has($otherUserStatsCacheKey))->toBeTrue()
        ->and(Cache::has($otherUserActivityCacheKey))->toBeTrue()
        ->and($trustedDeviceDashboardCache->stats($user)->total)->toBe(0)
        ->and($trustedDeviceDashboardCache->recentActivity($user, request()))->toBeEmpty()
        ->and($trustedDeviceDashboardCache->stats($otherUser)->toArray())->toBe($otherUsersStatsBefore)
        ->and($trustedDeviceDashboardCache->recentActivity($otherUser, request()))
        ->toBe($otherUsersActivityBefore);
});
