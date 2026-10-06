<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceRegistrationResult;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceStoreRequest;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceService;
use DeviceDetector\DeviceDetector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

function parsedChromeWindowsDetector(): DeviceDetector
{
    $deviceDetector = new DeviceDetector(chromeWindowsUserAgent());
    $deviceDetector->parse();

    return $deviceDetector;
}

function trustedDeviceStoreRequest(): TrustedDeviceStoreRequest
{
    return TrustedDeviceStoreRequest::create('/', 'POST', server: [
        'REMOTE_ADDR' => trustedDeviceFingerprint()['ip'],
        'HTTP_USER_AGENT' => trustedDeviceFingerprint()['user_agent'],
    ]);
}

it('renames the device, records a Renamed event and invalidates the dashboard cache', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user, ['name' => 'Old name']);
    warmTrustedDeviceStatsCache($user);

    resolve(TrustedDeviceService::class)->rename($user, $trustedDevice, 'New name', trustedDeviceAuditRequest());

    $trustedDeviceEvent = TrustedDeviceEvent::query()->where('user_id', $user->id)->sole();

    expect($trustedDevice->refresh()->name)->toBe('New name')
        ->and($trustedDeviceEvent->action)->toBe(TrustedDeviceAction::Renamed)
        ->and($trustedDeviceEvent->ip)->toBe(trustedDeviceFingerprint()['ip'])
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

it('reports AlreadyActive without creating anything when another request registers the device mid-flight', function (): void {
    $user = createUser();
    $fingerprint = trustedDeviceFingerprint();
    $deviceDetector = parsedChromeWindowsDetector();
    $trustedDeviceStoreRequest = trustedDeviceStoreRequest();

    $competingRequestRan = false;

    DB::beforeExecuting(function (string $query) use (&$competingRequestRan, $user, $fingerprint): void {
        if ($competingRequestRan || ! str_contains($query, 'is not null') || ! str_contains($query, 'deleted_at')) {
            return;
        }

        $competingRequestRan = true;
        createTrustedDevice($user, $fingerprint);
    });

    $trustedDeviceRegistrationResult = resolve(TrustedDeviceService::class)->register(
        $user,
        $deviceDetector,
        $trustedDeviceStoreRequest,
        hash('sha256', 'new-token'),
        '',
    );

    expect($competingRequestRan)->toBeTrue()
        ->and($trustedDeviceRegistrationResult)->toBe(TrustedDeviceRegistrationResult::AlreadyActive)
        ->and($trustedDeviceRegistrationResult->isSuccessful())->toBeFalse()
        ->and(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(countTrustedDeviceEvents($user, TrustedDeviceAction::Created))->toBe(0);
});

it('registers a new device, records a Created event and invalidates the dashboard cache', function (): void {
    $user = createUser();
    warmTrustedDeviceStatsCache($user);

    $trustedDeviceRegistrationResult = resolve(TrustedDeviceService::class)->register(
        $user,
        parsedChromeWindowsDetector(),
        trustedDeviceStoreRequest(),
        hash('sha256', 'new-token'),
        'Work laptop',
    );

    $trustedDevice = TrustedDevice::query()->where('user_id', $user->id)->sole();

    expect($trustedDeviceRegistrationResult)->toBe(TrustedDeviceRegistrationResult::Created)
        ->and($trustedDevice->name)->toBe('Work laptop')
        ->and($trustedDevice->token_hash)->toBe(hash('sha256', 'new-token'))
        ->and($trustedDevice->os_name)->toBe('Windows')
        ->and($trustedDevice->ip)->toBe(trustedDeviceFingerprint()['ip'])
        ->and(countTrustedDeviceEvents($user, TrustedDeviceAction::Created))->toBe(1)
        ->and(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeFalse();
});

it('prunes an expired device with the same fingerprint when registering', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user, [...trustedDeviceFingerprint(), 'expires_at' => Date::now()->subDay()]);

    resolve(TrustedDeviceService::class)->register(
        $user,
        parsedChromeWindowsDetector(),
        trustedDeviceStoreRequest(),
        hash('sha256', 'new-token'),
        '',
    );

    expect(TrustedDevice::query()->find($trustedDevice->id))->toBeNull()
        ->and(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('reports AlreadyActive without creating anything when the fingerprint is already trusted', function (): void {
    $user = createUser();
    createTrustedDevice($user, trustedDeviceFingerprint());
    warmTrustedDeviceStatsCache($user);

    $trustedDeviceRegistrationResult = resolve(TrustedDeviceService::class)->register(
        $user,
        parsedChromeWindowsDetector(),
        trustedDeviceStoreRequest(),
        hash('sha256', 'new-token'),
        '',
    );

    expect($trustedDeviceRegistrationResult)->toBe(TrustedDeviceRegistrationResult::AlreadyActive)
        ->and(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(TrustedDeviceEvent::query()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeTrue();
});

it('reports AlreadyRevoked without creating anything when the fingerprint was revoked', function (): void {
    $user = createUser();
    createTrustedDevice($user, trustedDeviceFingerprint())->delete();
    warmTrustedDeviceStatsCache($user);

    $trustedDeviceRegistrationResult = resolve(TrustedDeviceService::class)->register(
        $user,
        parsedChromeWindowsDetector(),
        trustedDeviceStoreRequest(),
        hash('sha256', 'new-token'),
        '',
    );

    expect($trustedDeviceRegistrationResult)->toBe(TrustedDeviceRegistrationResult::AlreadyRevoked)
        ->and(TrustedDevice::withTrashed()->where('user_id', $user->id)->count())->toBe(1)
        ->and(TrustedDeviceEvent::query()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeTrue();
});

it('reactivates a revoked device, records a Reactivated event and invalidates the dashboard cache', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user, ['expires_at' => Date::now()->subDay()]);
    $trustedDevice->delete();
    warmTrustedDeviceStatsCache($user);

    $rawToken = resolve(TrustedDeviceService::class)->reactivate($user, $trustedDevice, trustedDeviceAuditRequest());

    expect($trustedDevice->refresh()->deleted_at)->toBeNull()
        ->and($trustedDevice->isActive())->toBeTrue()
        ->and($trustedDevice->token_hash)->toBe(hash('sha256', $rawToken))
        ->and(countTrustedDeviceEvents($user, TrustedDeviceAction::Reactivated))->toBe(1)
        ->and(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeFalse();
});

it('revokes every device with one RevokedAll event each and invalidates the cache', function (): void {
    $user = createUser();
    createTrustedDevice($user);
    createTrustedDevice($user);
    warmTrustedDeviceStatsCache($user);

    $revokedCount = resolve(TrustedDeviceService::class)->revokeAll($user, trustedDeviceAuditRequest());

    expect($revokedCount)->toBe(2)
        ->and(countTrustedDeviceEvents($user, TrustedDeviceAction::RevokedAll))->toBe(2)
        ->and(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeFalse();
});

it('revokes nothing and leaves other users untouched when revoking all', function (): void {
    $user = createUser();
    $otherUser = createUser();
    $trustedDevice = createTrustedDevice($otherUser);
    warmTrustedDeviceStatsCache($user);

    $revokedCount = resolve(TrustedDeviceService::class)->revokeAll($user, trustedDeviceAuditRequest());

    expect($revokedCount)->toBe(0)
        ->and(TrustedDeviceEvent::query()->exists())->toBeFalse()
        ->and($trustedDevice->fresh()?->deleted_at)->toBeNull()
        ->and(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeTrue();
});

it('exposes a success payload for every completed action and an error payload for known devices', function (): void {
    foreach (TrustedDeviceRegistrationResult::cases() as $registrationResult) {
        $isError = \in_array($registrationResult, [
            TrustedDeviceRegistrationResult::AlreadyActive,
            TrustedDeviceRegistrationResult::AlreadyRevoked,
        ], true);

        expect($registrationResult->payload())->toBe([
            'type' => $isError ? 'error' : 'success',
            'message' => $registrationResult->message(),
        ])
            ->and($registrationResult->message())->not->toBeEmpty()
            ->and($registrationResult->isSuccessful())->toBe(! $isError);
    }
});
