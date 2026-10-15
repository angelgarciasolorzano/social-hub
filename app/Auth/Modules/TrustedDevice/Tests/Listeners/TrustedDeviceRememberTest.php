<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceDashboardCache;
use App\User\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Create a device for the browser the tests send (Chrome on Windows from 127.0.0.1).
 *
 * @param  array<string, mixed>  $attributes
 */
function createDeviceOfTestBrowser(User $user, array $attributes = []): TrustedDevice
{
    return createTrustedDevice($user, [
        'user_agent' => chromeWindowsUserAgent(),
        'os_name' => 'Windows',
        'ip' => '127.0.0.1',
        ...$attributes,
    ]);
}

/**
 * Pass the two-factor challenge from the test browser, asking to remember it.
 *
 * @return TestResponse<Response>
 */
function passChallengeRememberingBrowser(TestCase $testCase, User $user, bool $rememberDevice = true): TestResponse
{
    $otp = validOtpFor($user);
    allowOtpReuse($otp);

    Auth::logout();

    return $testCase->withSession(['login.id' => $user->id])
        ->withHeader('User-Agent', chromeWindowsUserAgent())
        ->post(route('two-factor.login.store'), ['code' => $otp, 'remember_device' => $rememberDevice]);
}

it('creates a trusted device and records a Created event when remember_device is checked', function (): void {
    $user = createUserWithTwoFactor();
    $otherUser = createUser();
    createTrustedDevice($otherUser);
    $trustedDeviceEvent = createTrustedDeviceEvent($otherUser);

    $trustedDeviceDashboardCache = resolve(TrustedDeviceDashboardCache::class);

    $userStatsCacheKey = trustedDeviceStatsCacheKey($user);
    $userActivityCacheKey = trustedDeviceRecentActivityCacheKey($user);
    $otherUserStatsCacheKey = trustedDeviceStatsCacheKey($otherUser);
    $otherUserActivityCacheKey = trustedDeviceRecentActivityCacheKey($otherUser);

    forgetTrustedDeviceDashboardCache($user, $otherUser);

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

    $userStatsCacheKey = trustedDeviceStatsCacheKey($user);
    $userActivityCacheKey = trustedDeviceRecentActivityCacheKey($user);
    $otherUserStatsCacheKey = trustedDeviceStatsCacheKey($otherUser);
    $otherUserActivityCacheKey = trustedDeviceRecentActivityCacheKey($otherUser);

    forgetTrustedDeviceDashboardCache($user, $otherUser);

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

it('leaves a single active device when a revoked browser is remembered again', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createDeviceOfTestBrowser($user);
    $trustedDevice->delete();

    passChallengeRememberingBrowser($this, $user)->assertRedirect();

    expect(TrustedDevice::withTrashed()->where('user_id', $user->id)->count())->toBe(1)
        ->and($trustedDevice->refresh()->deleted_at)->toBeNull()
        ->and($trustedDevice->isActive())->toBeTrue();
});

it('records a Reactivated event instead of a Created one when a revoked browser is remembered again', function (): void {
    $user = createUserWithTwoFactor();
    createDeviceOfTestBrowser($user)->delete();

    passChallengeRememberingBrowser($this, $user);

    expect(countTrustedDeviceEvents($user, TrustedDeviceAction::Reactivated))->toBe(1)
        ->and(countTrustedDeviceEvents($user, TrustedDeviceAction::Created))->toBe(0);
});

it('records a Renewed event when an expired browser is remembered again', function (): void {
    $user = createUserWithTwoFactor();
    createDeviceOfTestBrowser($user, ['expires_at' => now()->subDay()]);

    passChallengeRememberingBrowser($this, $user);

    expect(TrustedDevice::withTrashed()->where('user_id', $user->id)->count())->toBe(1)
        ->and(countTrustedDeviceEvents($user, TrustedDeviceAction::Renewed))->toBe(1);
});

it('does not add a second device when the same browser is remembered twice', function (): void {
    $user = createUserWithTwoFactor();

    passChallengeRememberingBrowser($this, $user);
    passChallengeRememberingBrowser($this, $user);

    expect(TrustedDevice::withTrashed()->where('user_id', $user->id)->count())->toBe(1);
});

it('issues a cookie whose hash matches the restored device', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createDeviceOfTestBrowser($user, ['token_hash' => hash('sha256', 'old-token')]);
    $trustedDevice->delete();

    $testResponse = passChallengeRememberingBrowser($this, $user);

    $cookie = $testResponse->getCookie('trusted_device');

    expect($cookie)->not->toBeNull()
        ->and($trustedDevice->refresh()->token_hash)->toBe(hash('sha256', (string) $cookie?->getValue()));
});

it('leaves a revoked browser untouched when remember_device is not checked', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createDeviceOfTestBrowser($user);
    $trustedDevice->delete();

    $testResponse = passChallengeRememberingBrowser($this, $user, rememberDevice: false);

    $testResponse->assertCookieMissing('trusted_device');

    expect($trustedDevice->refresh()->deleted_at)->not->toBeNull()
        ->and(TrustedDeviceEvent::query()->where('user_id', $user->id)->exists())->toBeFalse();
});
