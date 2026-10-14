<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Services;

use App\User\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Throwable;

final readonly class TwoFactorCodeVerifier
{
    public function __construct(private TwoFactorAuthenticationProvider $twoFactorAuthenticationProvider) {}

    /**
     * Whether the user has a confirmed two-factor secret; a pending secret
     * does not count as active two-factor authentication.
     */
    public function hasConfirmedTwoFactor(User $user): bool
    {
        return $user->hasEnabledTwoFactorAuthentication();
    }

    /**
     * Verify a TOTP code against the user's stored secret. Decryption
     * failures are logged without the code or the secret.
     */
    public function verifyTotp(User $user, string $code): bool
    {
        $encryptedSecret = $user->two_factor_secret;

        if (! \is_string($encryptedSecret) || $encryptedSecret === '' || $code === '') {
            return false;
        }

        try {
            $secret = Fortify::currentEncrypter()->decrypt($encryptedSecret);

            return \is_string($secret) && $this->twoFactorAuthenticationProvider->verify($secret, $code);
        } catch (Throwable $throwable) {
            Log::warning('TwoFactorCodeVerifier: TOTP secret could not be verified.', [
                'user_id' => $user->getKey(),
                'exception' => $throwable::class,
            ]);

            return false;
        }
    }

    /**
     * Consume a recovery code so it cannot be used again. Must run inside the
     * caller's DB::transaction: the user row is re-read with a write lock.
     */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        if ($code === '') {
            return false;
        }

        $lockedUser = User::query()->lockForUpdate()->find($user->getKey());

        if (! $lockedUser instanceof User) {
            return false;
        }

        $matchedCode = $this->findRecoveryCode($lockedUser, $code);

        if ($matchedCode === null) {
            return false;
        }

        $lockedUser->replaceRecoveryCode($matchedCode);

        $user->refresh();

        return true;
    }

    /**
     * Find the stored recovery code equal to the given one, comparing in
     * constant time. Returns null when none matches or the codes are unreadable.
     */
    private function findRecoveryCode(User $user, string $code): ?string
    {
        $encryptedRecoveryCodes = $user->two_factor_recovery_codes;

        if (! \is_string($encryptedRecoveryCodes) || $encryptedRecoveryCodes === '') {
            return null;
        }

        try {
            $recoveryCodes = $user->recoveryCodes();
        } catch (Throwable $throwable) {
            Log::warning('TwoFactorCodeVerifier: recovery codes could not be read.', [
                'user_id' => $user->getKey(),
                'exception' => $throwable::class,
            ]);

            return null;
        }

        foreach ($recoveryCodes as $recoveryCode) {
            if (\is_string($recoveryCode) && hash_equals($recoveryCode, $code)) {
                return $recoveryCode;
            }
        }

        return null;
    }
}
