<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceOperationResult;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceStoreRequest;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceService;
use App\User\Models\User;
use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

function rememberChromeWindowsBrowser(User $user, ?Request $request = null): string
{
    return resolve(TrustedDeviceService::class)->rememberAfterTwoFactorChallenge(
        $user,
        parsedChromeWindowsDetector(),
        $request ?? trustedDeviceAuditRequest(),
    );
}

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

function registerChromeWindowsDevice(User $user, string $name = ''): TrustedDeviceOperationResult
{
    return resolve(TrustedDeviceService::class)->register(
        $user,
        parsedChromeWindowsDetector(),
        trustedDeviceStoreRequest(),
        hash('sha256', 'new-token'),
        $name,
    );
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
    $user = createUserWithTwoFactor();
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
    $user = createUserWithTwoFactor();
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

    $trustedDeviceOperationResult = resolve(TrustedDeviceService::class)->register(
        $user,
        $deviceDetector,
        $trustedDeviceStoreRequest,
        hash('sha256', 'new-token'),
        '',
    );

    expect($competingRequestRan)->toBeTrue()
        ->and($trustedDeviceOperationResult)->toBe(TrustedDeviceOperationResult::AlreadyActive)
        ->and($trustedDeviceOperationResult->isSuccessful())->toBeFalse()
        ->and(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(countTrustedDeviceEvents($user, TrustedDeviceAction::Created))->toBe(0);
});

it('registers a new device, records a Created event and invalidates the dashboard cache', function (): void {
    $user = createUserWithTwoFactor();
    warmTrustedDeviceStatsCache($user);

    $trustedDeviceOperationResult = registerChromeWindowsDevice($user, 'Work laptop');

    $trustedDevice = TrustedDevice::query()->where('user_id', $user->id)->sole();

    expect($trustedDeviceOperationResult)->toBe(TrustedDeviceOperationResult::Created)
        ->and($trustedDevice->name)->toBe('Work laptop')
        ->and($trustedDevice->token_hash)->toBe(hash('sha256', 'new-token'))
        ->and($trustedDevice->os_name)->toBe('Windows')
        ->and($trustedDevice->ip)->toBe(trustedDeviceFingerprint()['ip'])
        ->and(countTrustedDeviceEvents($user, TrustedDeviceAction::Created))->toBe(1)
        ->and(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeFalse();
});

it('prunes an expired device with the same fingerprint when registering', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createTrustedDevice($user, [...trustedDeviceFingerprint(), 'expires_at' => Date::now()->subDay()]);

    registerChromeWindowsDevice($user);

    expect(TrustedDevice::query()->find($trustedDevice->id))->toBeNull()
        ->and(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('reports :dataset without creating anything when the fingerprint is already known', function (bool $isRevoked, TrustedDeviceOperationResult $expectedResult): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createTrustedDevice($user, trustedDeviceFingerprint());

    if ($isRevoked) {
        $trustedDevice->delete();
    }

    warmTrustedDeviceStatsCache($user);

    $trustedDeviceOperationResult = registerChromeWindowsDevice($user);

    expect($trustedDeviceOperationResult)->toBe($expectedResult)
        ->and(TrustedDevice::withTrashed()->where('user_id', $user->id)->count())->toBe(1)
        ->and(TrustedDeviceEvent::query()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeTrue();
})->with([
    'AlreadyActive' => [false, TrustedDeviceOperationResult::AlreadyActive],
    'AlreadyRevoked' => [true, TrustedDeviceOperationResult::AlreadyRevoked],
]);

it('reactivates a revoked device, records a Reactivated event and invalidates the dashboard cache', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createTrustedDevice($user, ['expires_at' => Date::now()->subDay()]);
    $trustedDevice->delete();
    warmTrustedDeviceStatsCache($user);

    $rawToken = resolve(TrustedDeviceService::class)->reactivate($user, $trustedDevice, validOtpFor($user), trustedDeviceAuditRequest());

    expect($trustedDevice->refresh()->deleted_at)->toBeNull()
        ->and($trustedDevice->isActive())->toBeTrue()
        ->and($trustedDevice->token_hash)->toBe(hash('sha256', $rawToken))
        ->and(countTrustedDeviceEvents($user, TrustedDeviceAction::Reactivated))->toBe(1)
        ->and(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeFalse();
});

it('does not reactivate a second device with a recovery code that a stale user model still holds', function (): void {
    $user = createUserWithRecoveryCodes('abcde12345-fghij67890');
    $staleUser = User::query()->whereKey($user->id)->firstOrFail();
    $trustedDevice = createTrustedDevice($user);
    $secondDevice = createTrustedDevice($user);
    $trustedDevice->delete();
    $secondDevice->delete();

    $trustedDeviceService = resolve(TrustedDeviceService::class);
    $trustedDeviceService->reactivate($user, $trustedDevice, 'abcde12345-fghij67890', trustedDeviceAuditRequest());

    expect(fn () => $trustedDeviceService->reactivate($staleUser, $secondDevice, 'abcde12345-fghij67890', trustedDeviceAuditRequest()))
        ->toThrow(ValidationException::class)
        ->and($secondDevice->refresh()->deleted_at)->not->toBeNull();
});

it('does not reactivate a device when two-factor was disabled after the user model was loaded', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createTrustedDevice($user);
    $trustedDevice->delete();

    User::query()->findOrFail($user->id)->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

    expect(fn () => resolve(TrustedDeviceService::class)->reactivate($user, $trustedDevice, '123456', trustedDeviceAuditRequest()))
        ->toThrow(ValidationException::class)
        ->and($trustedDevice->refresh()->deleted_at)->not->toBeNull()
        ->and(countTrustedDeviceEvents($user, TrustedDeviceAction::Reactivated))->toBe(0);
});

it('keeps the recovery codes and the revoked device untouched when the code is invalid', function (string $submittedCode): void {
    $user = createUserWithRecoveryCodes('abcde12345-fghij67890');
    $invalidCode = match ($submittedCode) {
        'wrong-totp' => invalidOtpFor($user),
        'unknown-recovery-code' => 'zzzzz00000-yyyyy11111',
        default => throw new InvalidArgumentException("Unknown submitted code '{$submittedCode}'."),
    };
    $trustedDevice = createTrustedDevice($user, ['token_hash' => hash('sha256', 'old-token')]);
    $trustedDevice->delete();

    expect(fn () => resolve(TrustedDeviceService::class)->reactivate($user, $trustedDevice, $invalidCode, trustedDeviceAuditRequest()))
        ->toThrow(ValidationException::class)
        ->and($user->refresh()->recoveryCodes())->toBe(['abcde12345-fghij67890'])
        ->and($trustedDevice->refresh()->deleted_at)->not->toBeNull()
        ->and($trustedDevice->token_hash)->toBe(hash('sha256', 'old-token'));
})->with([
    'wrong TOTP' => ['wrong-totp'],
    'unknown recovery code' => ['unknown-recovery-code'],
]);

it('gives the recovery code back when the reactivation fails after consuming it', function (): void {
    $user = createUserWithRecoveryCodes('abcde12345-fghij67890');
    $trustedDevice = createTrustedDevice($user);
    $trustedDevice->delete();

    TrustedDeviceEvent::creating(function (): never {
        throw new RuntimeException('audit failure');
    });

    try {
        expect(fn () => resolve(TrustedDeviceService::class)->reactivate($user, $trustedDevice, 'abcde12345-fghij67890', trustedDeviceAuditRequest()))
            ->toThrow(RuntimeException::class, 'audit failure');
    } finally {
        Event::forget('eloquent.creating: '.TrustedDeviceEvent::class);
    }

    expect($user->refresh()->recoveryCodes())->toBe(['abcde12345-fghij67890'])
        ->and($trustedDevice->refresh()->deleted_at)->not->toBeNull();
});

it('rejects registering a device when two-factor is disabled or pending', function (bool $hasPendingSecret): void {
    $user = $hasPendingSecret ? createUserWithTwoFactor() : createUser();

    if ($hasPendingSecret) {
        $user->forceFill(['two_factor_confirmed_at' => null])->save();
    }

    warmTrustedDeviceStatsCache($user);

    $trustedDeviceOperationResult = registerChromeWindowsDevice($user);

    expect($trustedDeviceOperationResult)->toBe(TrustedDeviceOperationResult::TwoFactorRequired)
        ->and(TrustedDevice::withTrashed()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(TrustedDeviceEvent::query()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeTrue();
})->with([
    'two-factor disabled' => [false],
    'pending secret' => [true],
]);

it('rejects registering a device when two-factor was disabled after the user model was loaded', function (): void {
    $user = createUserWithTwoFactor();

    User::query()->findOrFail($user->id)->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

    $trustedDeviceOperationResult = registerChromeWindowsDevice($user);

    expect($trustedDeviceOperationResult)->toBe(TrustedDeviceOperationResult::TwoFactorRequired)
        ->and(TrustedDevice::withTrashed()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(countTrustedDeviceEvents($user, TrustedDeviceAction::Created))->toBe(0);
});

it('returns Renewed when renewing a device with confirmed two-factor', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createTrustedDevice($user);

    $trustedDeviceOperationResult = resolve(TrustedDeviceService::class)->renew($user, $trustedDevice, trustedDeviceAuditRequest());

    expect($trustedDeviceOperationResult)->toBe(TrustedDeviceOperationResult::Renewed);
});

it('rejects renewing a device when two-factor is disabled or pending', function (bool $hasPendingSecret): void {
    $user = $hasPendingSecret ? createUserWithTwoFactor() : createUser();

    if ($hasPendingSecret) {
        $user->forceFill(['two_factor_confirmed_at' => null])->save();
    }

    $trustedDevice = createTrustedDevice($user, ['expires_at' => Date::now()->addHour()]);
    $expiresAt = $trustedDevice->refresh()->expires_at;
    warmTrustedDeviceStatsCache($user);

    $trustedDeviceOperationResult = resolve(TrustedDeviceService::class)->renew($user, $trustedDevice, trustedDeviceAuditRequest());

    expect($trustedDeviceOperationResult)->toBe(TrustedDeviceOperationResult::TwoFactorRequired)
        ->and($trustedDevice->refresh()->expires_at->equalTo($expiresAt))->toBeTrue()
        ->and(countTrustedDeviceEvents($user, TrustedDeviceAction::Renewed))->toBe(0)
        ->and(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeTrue();
})->with([
    'two-factor disabled' => [false],
    'pending secret' => [true],
]);

it('rejects renewing a device when two-factor was disabled after the user model was loaded', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createTrustedDevice($user, ['expires_at' => Date::now()->addHour()]);
    $expiresAt = $trustedDevice->refresh()->expires_at;

    User::query()->findOrFail($user->id)->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

    $trustedDeviceOperationResult = resolve(TrustedDeviceService::class)->renew($user, $trustedDevice, trustedDeviceAuditRequest());

    expect($trustedDeviceOperationResult)->toBe(TrustedDeviceOperationResult::TwoFactorRequired)
        ->and($trustedDevice->refresh()->expires_at->equalTo($expiresAt))->toBeTrue()
        ->and(countTrustedDeviceEvents($user, TrustedDeviceAction::Renewed))->toBe(0);
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

it('exposes a success payload for every completed action and an error payload for rejected ones', function (): void {
    foreach (TrustedDeviceOperationResult::cases() as $operationResult) {
        $isError = \in_array($operationResult, [
            TrustedDeviceOperationResult::AlreadyActive,
            TrustedDeviceOperationResult::AlreadyRevoked,
            TrustedDeviceOperationResult::TwoFactorRequired,
        ], true);

        expect($operationResult->payload())->toBe([
            'type' => $isError ? 'error' : 'success',
            'message' => $operationResult->message(),
        ])
            ->and($operationResult->message())->not->toBeEmpty()
            ->and($operationResult->isSuccessful())->toBe(! $isError);
    }
});

it('creates a device with a Created event when the browser was never seen', function (): void {
    $user = createUserWithTwoFactor();

    rememberChromeWindowsBrowser($user);

    expect(TrustedDevice::withTrashed()->where('user_id', $user->id)->count())->toBe(1)
        ->and(countTrustedDeviceEvents($user, TrustedDeviceAction::Created))->toBe(1);
});

it('restores the revoked device of the same browser instead of adding a second one', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createTrustedDevice($user, trustedDeviceFingerprint());
    $trustedDevice->delete();

    rememberChromeWindowsBrowser($user);

    expect(TrustedDevice::withTrashed()->where('user_id', $user->id)->count())->toBe(1)
        ->and($trustedDevice->refresh()->deleted_at)->toBeNull()
        ->and($trustedDevice->isActive())->toBeTrue();
});

it('records a Reactivated event and no Created event when it restores a revoked device', function (): void {
    $user = createUserWithTwoFactor();
    createTrustedDevice($user, trustedDeviceFingerprint())->delete();

    rememberChromeWindowsBrowser($user);

    expect(countTrustedDeviceEvents($user, TrustedDeviceAction::Reactivated))->toBe(1)
        ->and(countTrustedDeviceEvents($user, TrustedDeviceAction::Created))->toBe(0);
});

it('renews the expired device of the same browser instead of adding a second one', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createTrustedDevice($user, [...trustedDeviceFingerprint(), 'expires_at' => Date::now()->subDay()]);

    rememberChromeWindowsBrowser($user);

    expect(TrustedDevice::withTrashed()->where('user_id', $user->id)->count())->toBe(1)
        ->and($trustedDevice->refresh()->isActive())->toBeTrue()
        ->and($trustedDevice->deleted_at)->toBeNull()
        ->and(countTrustedDeviceEvents($user, TrustedDeviceAction::Renewed))->toBe(1);
});

it('renews the active device of the same browser instead of adding a second one', function (): void {
    $user = createUserWithTwoFactor();
    createTrustedDevice($user, trustedDeviceFingerprint());

    rememberChromeWindowsBrowser($user);

    expect(TrustedDevice::withTrashed()->where('user_id', $user->id)->count())->toBe(1)
        ->and(countTrustedDeviceEvents($user, TrustedDeviceAction::Renewed))->toBe(1);
});

it('returns a new token that validates the next login and invalidates the old one', function (): void {
    $user = createUserWithTwoFactor();
    createTrustedDevice($user, [...trustedDeviceFingerprint(), 'token_hash' => hash('sha256', 'old-token')])->delete();

    $newToken = rememberChromeWindowsBrowser($user);

    expect(TrustedDevice::validateAndTouch($user, Request::create('/', 'POST', cookies: ['trusted_device' => $newToken])))->toBeTrue()
        ->and(TrustedDevice::validateAndTouch($user, Request::create('/', 'POST', cookies: ['trusted_device' => 'old-token'])))->toBeFalse();
});

it('does not touch the device of another user with the same fingerprint', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createTrustedDevice(createUser(), trustedDeviceFingerprint());
    $trustedDevice->delete();

    rememberChromeWindowsBrowser($user);

    expect($trustedDevice->fresh()?->deleted_at)->not->toBeNull()
        ->and(TrustedDevice::withTrashed()->where('user_id', $user->id)->count())->toBe(1);
});

it('does not reuse a device when the request has no IP address', function (): void {
    $user = createUserWithTwoFactor();
    createTrustedDevice($user, trustedDeviceFingerprint())->delete();

    $request = trustedDeviceAuditRequest();
    $request->server->remove('REMOTE_ADDR');

    rememberChromeWindowsBrowser($user, $request);

    expect(TrustedDevice::withTrashed()->where('user_id', $user->id)->count())->toBe(2);
});

it('invalidates the dashboard cache when it remembers the browser', function (): void {
    $user = createUserWithTwoFactor();
    warmTrustedDeviceStatsCache($user);

    rememberChromeWindowsBrowser($user);

    expect(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeFalse();
});

it('keeps the device revoked and the cache warm when recording the event fails', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createTrustedDevice($user, trustedDeviceFingerprint());
    $trustedDevice->delete();
    warmTrustedDeviceStatsCache($user);

    TrustedDeviceEvent::creating(function (): never {
        throw new RuntimeException('audit failure');
    });

    try {
        expect(fn (): string => rememberChromeWindowsBrowser($user))->toThrow(RuntimeException::class, 'audit failure');
    } finally {
        Event::forget('eloquent.creating: '.TrustedDeviceEvent::class);
    }

    expect($trustedDevice->fresh()?->deleted_at)->not->toBeNull()
        ->and(Cache::has(trustedDeviceStatsCacheKey($user)))->toBeTrue();
});
