<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Services;

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Concerns\InfersDeviceMetadata;
use App\Auth\Modules\TrustedDevice\Concerns\MintsTrustedDeviceToken;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceOperationResult;
use App\Auth\Modules\TrustedDevice\Requests\TrustedDeviceStoreRequest;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TrustedDeviceService
{
    use InfersDeviceMetadata;
    use MintsTrustedDeviceToken;

    public function __construct(
        private TrustedDeviceDashboardCache $trustedDeviceDashboardCache,
        private TwoFactorCodeVerifier $twoFactorCodeVerifier,
    ) {}

    public function create(
        User $user,
        DeviceDetector $deviceDetector,
        string $tokenHash,
        ?string $name,
        ?string $userAgent,
        ?string $ip,
    ): TrustedDevice {
        $osInfo = $this->inferOsInfo($deviceDetector);

        $now = CarbonImmutable::now();

        return $user->trustedDevices()->create([
            'name' => $name !== null && $name !== ''
                ? $name
                : $this->inferDeviceName($deviceDetector),
            'token_hash' => $tokenHash,
            'user_agent' => $userAgent,
            'browser' => $this->inferBrowserName($deviceDetector),
            'browser_version' => $this->inferBrowserVersion($deviceDetector),
            'os_name' => $osInfo['name'],
            'os_version' => $osInfo['version'],
            'is_mobile' => $this->inferIsMobile($deviceDetector),
            'ip' => $ip,
            'last_used_at' => $now,
            'expires_at' => $now->addMinutes($this->cookieLifetimeMinutes()),
        ]);
    }

    public function register(
        User $user,
        DeviceDetector $deviceDetector,
        TrustedDeviceStoreRequest $trustedDeviceStoreRequest,
        string $tokenHash,
        string $name,
    ): TrustedDeviceOperationResult {
        if (! $this->twoFactorCodeVerifier->hasConfirmedTwoFactor($user)) {
            return TrustedDeviceOperationResult::TwoFactorRequired;
        }

        $userAgent = $trustedDeviceStoreRequest->userAgent();
        $ip = $trustedDeviceStoreRequest->ip();
        $osName = $this->inferOsInfo($deviceDetector)['name'];

        if ($userAgent !== null && $ip !== null) {
            $existingMatch = TrustedDevice::findAnyMatchForFingerprint($user, $userAgent, $osName, $ip);

            if ($existingMatch instanceof TrustedDevice) {
                return $existingMatch->deleted_at !== null
                    ? TrustedDeviceOperationResult::AlreadyRevoked
                    : TrustedDeviceOperationResult::AlreadyActive;
            }
        }

        return DB::transaction(function () use (
            $user,
            $deviceDetector,
            $trustedDeviceStoreRequest,
            $tokenHash,
            $name,
            $userAgent,
            $ip,
            $osName,
        ): TrustedDeviceOperationResult {
            if (! $this->lockedUserHasConfirmedTwoFactor($user)) {
                return TrustedDeviceOperationResult::TwoFactorRequired;
            }

            if ($userAgent !== null && $ip !== null) {
                $concurrentMatch = TrustedDevice::findActiveMatch($user, $userAgent, $osName, $ip, lockForUpdate: true);

                if ($concurrentMatch instanceof TrustedDevice) {
                    return TrustedDeviceOperationResult::AlreadyActive;
                }
            }

            $trustedDevice = $this->create($user, $deviceDetector, $tokenHash, $name, $userAgent, $ip);

            if ($userAgent !== null) {
                TrustedDevice::pruneOlder($user, $userAgent, $osName, $ip, $trustedDevice->id);
            }

            $this->recordEvent($user, $trustedDevice, TrustedDeviceAction::Created, $trustedDeviceStoreRequest);

            return TrustedDeviceOperationResult::Created;
        });
    }

    /**
     * Trust the current browser after a successful two-factor challenge and
     * return the raw token for its cookie. The device row of the same
     * fingerprint is reused (restored if revoked) instead of adding a second
     * one; only a browser never seen before gets a new row.
     */
    public function rememberAfterTwoFactorChallenge(User $user, DeviceDetector $deviceDetector, Request $request): string
    {
        return DB::transaction(function () use ($user, $deviceDetector, $request): string {
            $userAgent = $request->userAgent();
            $ip = $request->ip();
            $osName = $this->inferOsInfo($deviceDetector)['name'];
            $newToken = $this->mintToken();

            $existingDevice = $userAgent !== null
                ? TrustedDevice::findLatestForFingerprint($user, $userAgent, $osName, $ip, lockForUpdate: true)
                : null;

            if ($existingDevice instanceof TrustedDevice) {
                $trustedDeviceAction = $existingDevice->trashed() ? TrustedDeviceAction::Reactivated : TrustedDeviceAction::Renewed;
                $trustedDevice = $this->refreshTrust($existingDevice, $newToken['hash']);
            } else {
                $trustedDeviceAction = TrustedDeviceAction::Created;
                $trustedDevice = $this->create($user, $deviceDetector, $newToken['hash'], null, $userAgent, $ip);
            }

            if ($userAgent !== null && $ip !== null) {
                TrustedDevice::pruneOlder($user, $userAgent, $osName, $ip, $trustedDevice->id);
            }

            $this->recordEvent($user, $trustedDevice, $trustedDeviceAction, $request);

            return $newToken['token'];
        });
    }

    public function rename(User $user, TrustedDevice $trustedDevice, string $name, Request $request): void
    {
        DB::transaction(function () use ($user, $trustedDevice, $name, $request): void {
            $trustedDevice->forceFill(['name' => $name])->save();

            $this->recordEvent($user, $trustedDevice, TrustedDeviceAction::Renamed, $request);
        });
    }

    public function renew(User $user, TrustedDevice $trustedDevice, Request $request): TrustedDeviceOperationResult
    {
        if (! $this->twoFactorCodeVerifier->hasConfirmedTwoFactor($user)) {
            return TrustedDeviceOperationResult::TwoFactorRequired;
        }

        return DB::transaction(function () use ($user, $trustedDevice, $request): TrustedDeviceOperationResult {
            if (! $this->lockedUserHasConfirmedTwoFactor($user)) {
                return TrustedDeviceOperationResult::TwoFactorRequired;
            }

            $trustedDevice->forceFill([
                'expires_at' => CarbonImmutable::now()->addMinutes($this->cookieLifetimeMinutes()),
            ])->save();

            $this->recordEvent($user, $trustedDevice, TrustedDeviceAction::Renewed, $request);

            return TrustedDeviceOperationResult::Renewed;
        });
    }

    public function revoke(User $user, TrustedDevice $trustedDevice, Request $request): void
    {
        DB::transaction(function () use ($user, $trustedDevice, $request): void {
            $this->recordEvent($user, $trustedDevice, TrustedDeviceAction::Revoked, $request);

            $trustedDevice->delete();
        });
    }

    public function forceDelete(User $user, TrustedDevice $trustedDevice, Request $request): void
    {
        DB::transaction(function () use ($user, $trustedDevice, $request): void {
            $this->recordEvent($user, $trustedDevice, TrustedDeviceAction::Revoked, $request);

            $trustedDevice->forceDelete();
        });
    }

    /**
     * Restore a revoked device after verifying the code inside the same
     * transaction. A recovery code is consumed here, so a failure further down
     * rolls the consumption back too.
     *
     * @throws ValidationException
     */
    public function reactivate(User $user, TrustedDevice $trustedDevice, string $otpCode, Request $request): string
    {
        return DB::transaction(function () use ($user, $trustedDevice, $otpCode, $request): string {
            $this->assertReactivationCodeIsValid($user, $otpCode);

            $this->dropDuplicateActiveDevice($user, $trustedDevice, $request);

            $newToken = $this->mintToken();

            $this->refreshTrust($trustedDevice, $newToken['hash']);

            $this->recordEvent($user, $trustedDevice, TrustedDeviceAction::Reactivated, $request);

            return $newToken['token'];
        });
    }

    public function revokeAll(User $user, Request $request): int
    {
        return DB::transaction(function () use ($user, $request): int {
            $trustedDevices = $user->trustedDevices()
                ->latest('last_used_at')
                ->lockForUpdate()
                ->get();

            if ($trustedDevices->isEmpty()) {
                return 0;
            }

            foreach ($trustedDevices as $trustedDevice) {
                TrustedDeviceEvent::record(
                    trustedDevice: $trustedDevice,
                    user: $user,
                    trustedDeviceAction: TrustedDeviceAction::RevokedAll,
                    request: $request,
                );
            }

            $user->trustedDevices()->delete();

            $this->invalidateDashboardAfterCommit($user);

            return $trustedDevices->count();
        });
    }

    /**
     * Require confirmed two-factor and a valid code (a TOTP or a single-use
     * recovery code, see TwoFactorCodeVerifier::verifyCode).
     *
     * @throws ValidationException
     */
    private function assertReactivationCodeIsValid(User $user, string $otpCode): void
    {
        $lockedUser = User::query()->lockForUpdate()->find($user->getKey());

        if (! $lockedUser instanceof User || ! $this->twoFactorCodeVerifier->hasConfirmedTwoFactor($lockedUser)) {
            throw ValidationException::withMessages([
                'two_factor' => TrustedDeviceOperationResult::TwoFactorRequired->message(),
            ]);
        }

        if (! $this->twoFactorCodeVerifier->verifyCode($lockedUser, $otpCode)) {
            throw ValidationException::withMessages([
                'otp_code' => 'El código de verificación es incorrecto.',
            ]);
        }
    }

    /**
     * Give an existing device a new token and a new trust window, restoring
     * it when it was revoked.
     */
    private function refreshTrust(TrustedDevice $trustedDevice, string $tokenHash): TrustedDevice
    {
        $now = CarbonImmutable::now();

        $trustedDevice->forceFill([
            'token_hash' => $tokenHash,
            'expires_at' => $now->addMinutes($this->cookieLifetimeMinutes()),
            'last_used_at' => $now,
        ])->save();

        if ($trustedDevice->trashed()) {
            $trustedDevice->restore();
        }

        return $trustedDevice;
    }

    /**
     * Re-read the user with a write lock so a concurrent request that disables
     * two-factor cannot leave a trusted device behind. Call inside a transaction.
     */
    private function lockedUserHasConfirmedTwoFactor(User $user): bool
    {
        $lockedUser = User::query()->lockForUpdate()->find($user->getKey());

        return $lockedUser instanceof User && $this->twoFactorCodeVerifier->hasConfirmedTwoFactor($lockedUser);
    }

    /**
     * Record the audit event now and refresh the dashboard cache once the
     * surrounding transaction commits.
     */
    private function recordEvent(
        User $user,
        TrustedDevice $trustedDevice,
        TrustedDeviceAction $trustedDeviceAction,
        Request $request,
    ): void {
        TrustedDeviceEvent::record(
            trustedDevice: $trustedDevice,
            user: $user,
            trustedDeviceAction: $trustedDeviceAction,
            request: $request,
        );

        $this->invalidateDashboardAfterCommit($user);
    }

    /**
     * Hard-delete any active sibling sharing the same fingerprint before a
     * reactivate, locked against concurrent reads and recorded as Revoked.
     */
    private function dropDuplicateActiveDevice(User $user, TrustedDevice $trustedDevice, Request $request): void
    {
        $duplicate = TrustedDevice::findActiveMatch(
            $user,
            $trustedDevice->user_agent,
            $trustedDevice->os_name,
            $trustedDevice->ip,
            lockForUpdate: true,
        );

        if ($duplicate instanceof TrustedDevice && $duplicate->id !== $trustedDevice->id) {
            $this->recordEvent($user, $duplicate, TrustedDeviceAction::Revoked, $request);

            $duplicate->forceDelete();
        }
    }

    /**
     * Forget the user's dashboard cache once the surrounding transaction
     * commits, so a rolled-back change never clears a still-valid cache.
     */
    private function invalidateDashboardAfterCommit(User $user): void
    {
        DB::afterCommit(function () use ($user): void {
            $this->trustedDeviceDashboardCache->invalidate($user);
        });
    }

    /**
     * Read how many minutes a trusted device (and its cookie) stays valid
     * from `module.auth.trusted_devices.cookie_lifetime_minutes`.
     */
    private function cookieLifetimeMinutes(): int
    {
        /** @var int $cookieLifetimeMinutes */
        $cookieLifetimeMinutes = config('module.auth.trusted_devices.cookie_lifetime_minutes');

        return $cookieLifetimeMinutes;
    }
}
