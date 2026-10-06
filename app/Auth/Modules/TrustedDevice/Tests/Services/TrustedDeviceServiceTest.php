<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceDashboardCache;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceService;
use App\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;

function trustedDeviceStatsCacheKey(User $user): string
{
    return \sprintf('trusted-device:dashboard:%s:stats', $user->id);
}

function warmTrustedDeviceStatsCache(User $user): void
{
    resolve(TrustedDeviceDashboardCache::class)->stats($user);

    expect(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeTrue();
}

function trustedDeviceAuditRequest(): Request
{
    return Request::create('/', 'POST', server: [
        'REMOTE_ADDR' => '203.0.113.10',
        'HTTP_USER_AGENT' => chromeWindowsUserAgent(),
    ]);
}

function countTrustedDeviceEvents(User $user, TrustedDeviceAction $trustedDeviceAction): int
{
    return TrustedDeviceEvent::query()
        ->where('user_id', $user->id)
        ->where('action', $trustedDeviceAction)
        ->count();
}

it('renames the device, records a Renamed event and invalidates the dashboard cache', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user, ['name' => 'Old name']);
    warmTrustedDeviceStatsCache($user);

    resolve(TrustedDeviceService::class)->rename($user, $trustedDevice, 'New name', trustedDeviceAuditRequest());

    $trustedDeviceEvent = TrustedDeviceEvent::query()->where('user_id', $user->id)->sole();

    expect($trustedDevice->refresh()->name)->toBe('New name')
        ->and($trustedDeviceEvent->action)->toBe(TrustedDeviceAction::Renamed)
        ->and($trustedDeviceEvent->ip)->toBe('203.0.113.10')
        ->and(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeFalse();
});

it('renews the expiration, records a Renewed event and invalidates the dashboard cache', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user, ['expires_at' => Date::now()->addHour()]);
    warmTrustedDeviceStatsCache($user);

    resolve(TrustedDeviceService::class)->renew($user, $trustedDevice, trustedDeviceAuditRequest());

    /** @var int $cookieLifetimeMinutes */
    $cookieLifetimeMinutes = config('module.auth.trusted_devices.cookie_lifetime_minutes');

    expect($trustedDevice->refresh()->expires_at)
        ->toBeGreaterThan(Date::now()->addMinutes($cookieLifetimeMinutes - 1))
        ->and(countTrustedDeviceEvents($user, TrustedDeviceAction::Renewed))->toBe(1)
        ->and(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeFalse();
});

it('soft deletes the device, records a Revoked event and invalidates the dashboard cache', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user);
    warmTrustedDeviceStatsCache($user);

    resolve(TrustedDeviceService::class)->revoke($user, $trustedDevice, trustedDeviceAuditRequest());

    expect(TrustedDevice::query()->find($trustedDevice->id))->toBeNull()
        ->and(TrustedDevice::withTrashed()->find($trustedDevice->id))->not->toBeNull()
        ->and(countTrustedDeviceEvents($user, TrustedDeviceAction::Revoked))->toBe(1)
        ->and(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeFalse();
});

it('permanently deletes a revoked device, records a Revoked event and invalidates the dashboard cache', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user);
    $trustedDevice->delete();
    warmTrustedDeviceStatsCache($user);

    resolve(TrustedDeviceService::class)->forceDelete($user, $trustedDevice, trustedDeviceAuditRequest());

    expect(TrustedDevice::withTrashed()->find($trustedDevice->id))->toBeNull()
        ->and(countTrustedDeviceEvents($user, TrustedDeviceAction::Revoked))->toBe(1)
        ->and(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeFalse();
});

it('rolls back the change and keeps the cache warm when recording the event fails', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user, ['name' => 'Original']);
    warmTrustedDeviceStatsCache($user);

    TrustedDeviceEvent::creating(function (): never {
        throw new RuntimeException('audit failure');
    });

    try {
        expect(fn () => resolve(TrustedDeviceService::class)->rename(
            $user,
            $trustedDevice,
            'Changed',
            trustedDeviceAuditRequest(),
        ))->toThrow(RuntimeException::class, 'audit failure');
    } finally {
        Event::forget('eloquent.creating: '.TrustedDeviceEvent::class);
    }

    expect($trustedDevice->refresh()->name)->toBe('Original')
        ->and(TrustedDeviceEvent::query()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeTrue();
});
