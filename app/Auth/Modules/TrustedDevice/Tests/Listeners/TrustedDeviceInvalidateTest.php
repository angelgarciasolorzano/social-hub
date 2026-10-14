<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceDashboardCache;
use App\User\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

it('revokes every trusted device and records a RevokedAll event for each when 2FA is disabled', function (): void {
    $user = createUserWithTwoFactor();
    createTrustedDevice($user, ['name' => 'MacBook']);
    createTrustedDevice($user, ['name' => 'iPhone']);

    $otherUser = createUser();
    createTrustedDevice($otherUser);
    createTrustedDeviceEvent($otherUser);

    $trustedDeviceDashboardCache = resolve(TrustedDeviceDashboardCache::class);

    $userStatsCacheKey = trustedDeviceStatsCacheKey($user);
    $userActivityCacheKey = trustedDeviceRecentActivityCacheKey($user);
    $otherUserStatsCacheKey = trustedDeviceStatsCacheKey($otherUser);
    $otherUserActivityCacheKey = trustedDeviceRecentActivityCacheKey($otherUser);

    forgetTrustedDeviceDashboardCache($user, $otherUser);

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

    $userStatsCacheKey = trustedDeviceStatsCacheKey($user);
    $userActivityCacheKey = trustedDeviceRecentActivityCacheKey($user);
    $otherUserStatsCacheKey = trustedDeviceStatsCacheKey($otherUser);
    $otherUserActivityCacheKey = trustedDeviceRecentActivityCacheKey($otherUser);

    forgetTrustedDeviceDashboardCache($user, $otherUser);

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

function createUserWithPendingTwoFactor(): User
{
    $user = createUserWithTwoFactor();
    $user->forceFill(['two_factor_confirmed_at' => null])->save();

    return $user;
}

/**
 * @return array<string, int>
 */
function confirmedPasswordSession(): array
{
    return ['auth.password_confirmed_at' => Date::now()
        ->getTimestamp()];
}

it('revokes every trusted device and records a RevokedAll event for each when 2FA is confirmed', function (): void {
    $user = createUserWithPendingTwoFactor();
    createTrustedDevice($user, ['name' => 'MacBook']);
    createTrustedDevice($user, ['name' => 'iPhone']);

    $this->actingAs($user)
        ->withSession(confirmedPasswordSession())
        ->post(route('two-factor.confirm'), ['code' => validOtpFor($user)])
        ->assertRedirect();

    expect($user->refresh()->two_factor_confirmed_at)->not->toBeNull()
        ->and(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and(TrustedDevice::withTrashed()->where('user_id', $user->id)->count())->toBe(2)
        ->and(TrustedDeviceEvent::query()
            ->where('user_id', $user->id)
            ->where('action', TrustedDeviceAction::RevokedAll)
            ->count())->toBe(2);
});

it('invalidates the dashboard cache of the user who confirms 2FA', function (): void {
    $user = createUserWithPendingTwoFactor();
    createTrustedDevice($user);

    $trustedDeviceDashboardCache = resolve(TrustedDeviceDashboardCache::class);

    forgetTrustedDeviceDashboardCache($user);
    $trustedDeviceDashboardCache->stats($user);

    $this->actingAs($user)
        ->withSession(confirmedPasswordSession())
        ->post(route('two-factor.confirm'), ['code' => validOtpFor($user)])
        ->assertRedirect();

    expect(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeFalse()
        ->and($trustedDeviceDashboardCache->stats($user)->total)->toBe(0);
});

it('leaves the trusted devices of other users untouched when 2FA is confirmed', function (): void {
    $user = createUserWithPendingTwoFactor();
    createTrustedDevice($user);

    $otherUser = createUser();
    $trustedDevice = createTrustedDevice($otherUser);

    $this->actingAs($user)
        ->withSession(confirmedPasswordSession())
        ->post(route('two-factor.confirm'), ['code' => validOtpFor($user)])
        ->assertRedirect();

    expect($trustedDevice->fresh()?->deleted_at)->toBeNull();
});

it('does not revoke trusted devices when the 2FA confirmation code is invalid', function (): void {
    $user = createUserWithPendingTwoFactor();
    $trustedDevice = createTrustedDevice($user);

    $this->actingAs($user)
        ->withSession(confirmedPasswordSession())
        ->post(route('two-factor.confirm'), ['code' => '000000']);

    expect($user->refresh()->two_factor_confirmed_at)->toBeNull()
        ->and($trustedDevice->fresh()?->deleted_at)->toBeNull();
});

it('does not let a cookie issued before 2FA was confirmed skip the OTP challenge afterwards', function (): void {
    $user = createUserWithPendingTwoFactor();
    createTrustedDevice($user, ['token_hash' => hash('sha256', 'pre-confirmation-token')]);

    $this->actingAs($user)
        ->withSession(confirmedPasswordSession())
        ->post(route('two-factor.confirm'), ['code' => validOtpFor($user)])
        ->assertRedirect();
    Auth::logout();

    $this->withCookie('trusted_device', 'pre-confirmation-token')
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});
