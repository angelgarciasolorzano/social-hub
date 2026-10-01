<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\Password\Requests\PasswordRequest;
use App\Auth\Modules\Password\Services\PasswordTrustedDeviceRevoker;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceDashboardCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('invalidates trusted device dashboard caches after the enclosing transaction commits', function (): void {
    $user = createUser();
    createTrustedDevice($user);

    $trustedDeviceDashboardCache = resolve(TrustedDeviceDashboardCache::class);
    $userStatsCacheKey = 'trusted-device:dashboard:'.$user->id.':stats';
    $userActivityCacheKey = 'trusted-device:dashboard:'.$user->id.':recent-activity';
    $passwordRequest = PasswordRequest::create('/setting/password', 'PUT');

    Cache::forget($userStatsCacheKey);
    Cache::forget($userActivityCacheKey);

    $trustedDeviceDashboardCache->stats($user);
    $trustedDeviceDashboardCache->recentActivity($user, $passwordRequest);

    DB::transaction(function () use (
        $passwordRequest,
        $user,
        $userActivityCacheKey,
        $userStatsCacheKey,
    ): void {
        $revokedDevicesCount = resolve(PasswordTrustedDeviceRevoker::class)
            ->revokeAll($user, $passwordRequest);

        expect($revokedDevicesCount)->toBe(1)
            ->and(Cache::has($userStatsCacheKey))->toBeTrue()
            ->and(Cache::has($userActivityCacheKey))->toBeTrue();
    });

    expect(Cache::has($userStatsCacheKey))->toBeFalse()
        ->and(Cache::has($userActivityCacheKey))->toBeFalse();
});

it("revokes the authenticated user's trusted devices without affecting other users", function (): void {
    $user = createUser();
    $firstTrustedDevice = createTrustedDevice($user, ['name' => 'Personal phone']);
    $secondTrustedDevice = createTrustedDevice($user, ['name' => 'Work laptop']);

    $otherUser = createUser();
    $otherUsersTrustedDevice = createTrustedDevice($otherUser);
    createTrustedDeviceEvent($otherUser, ['action' => TrustedDeviceAction::Created]);

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

    $expectedIp = '203.0.113.25';
    $expectedUserAgent = 'Password change regression test';

    $testResponse = $this->withServerVariables(['REMOTE_ADDR' => $expectedIp])
        ->withHeaders(['User-Agent' => $expectedUserAgent])
        ->actingAs($user)
        ->from(route('setting.password.edit'))
        ->put(route('setting.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $testResponse
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('setting.password.edit'));

    $this->assertSoftDeleted($firstTrustedDevice);
    $this->assertSoftDeleted($secondTrustedDevice);

    $revocationEvents = TrustedDeviceEvent::query()
        ->where('user_id', $user->id)
        ->where('action', TrustedDeviceAction::RevokedAll)
        ->get();

    expect($user->trustedDevices()->exists())->toBeFalse()
        ->and($otherUser->trustedDevices()->whereKey($otherUsersTrustedDevice->getKey())
            ->exists())
        ->toBeTrue()
        ->and($revocationEvents)->toHaveCount(2)
        ->and($revocationEvents->pluck('device_label')->all())->toContain('Personal phone', 'Work laptop')
        ->and($revocationEvents->pluck('ip')->unique()->values()->all())->toBe([$expectedIp])
        ->and($revocationEvents->pluck('user_agent')->unique()->values()->all())->toBe([$expectedUserAgent])
        ->and(TrustedDeviceEvent::query()->where('user_id', $otherUser->id)->count())->toBe(1)
        ->and(Cache::has($userStatsCacheKey))->toBeFalse()
        ->and(Cache::has($userActivityCacheKey))->toBeFalse()
        ->and(Cache::has($otherUserStatsCacheKey))->toBeTrue()
        ->and(Cache::has($otherUserActivityCacheKey))->toBeTrue()
        ->and($trustedDeviceDashboardCache->stats($user)->revoked)->toBe(2)
        ->and(array_column($trustedDeviceDashboardCache->recentActivity($user, request()), 'action'))
        ->toBe([TrustedDeviceAction::RevokedAll->value, TrustedDeviceAction::RevokedAll->value])
        ->and($trustedDeviceDashboardCache->stats($otherUser)->toArray())->toBe($otherUsersStatsBefore)
        ->and($trustedDeviceDashboardCache->recentActivity($otherUser, request()))
        ->toBe($otherUsersActivityBefore);
});

it('keeps every trusted device active when the current password is incorrect', function (): void {
    $user = createUser();
    $firstTrustedDevice = createTrustedDevice($user);
    $secondTrustedDevice = createTrustedDevice($user);

    $testResponse = $this->actingAs($user)
        ->from(route('setting.password.edit'))
        ->put(route('setting.password.update'), [
            'current_password' => 'incorrect-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $testResponse
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('setting.password.edit'));

    $this->assertNotSoftDeleted($firstTrustedDevice);
    $this->assertNotSoftDeleted($secondTrustedDevice);

    expect($user->trustedDevices()->count())->toBe(2)
        ->and(TrustedDeviceEvent::query()
            ->where('user_id', $user->id)
            ->where('action', TrustedDeviceAction::RevokedAll)
            ->count())
        ->toBe(0);
});
