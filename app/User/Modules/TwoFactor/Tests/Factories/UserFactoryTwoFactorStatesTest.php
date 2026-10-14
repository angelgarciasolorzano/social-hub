<?php

declare(strict_types=1);

use App\Auth\Modules\TrustedDevice\Services\TwoFactorCodeVerifier;
use App\User\Database\Factories\UserFactory;
use App\User\Models\User;

const FORTIFY_RECOVERY_CODE_PATTERN = '/^[A-Za-z0-9]{10}-[A-Za-z0-9]{10}$/';

function createUserWithPendingTwoFactorState(): User
{
    return UserFactory::new()->twoFactorPending()->createOne();
}

it('creates users without two-factor authentication by default', function (): void {
    $user = createUser();

    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

it('creates a user with confirmed two-factor authentication', function (): void {
    $user = createUserWithTwoFactor();

    expect($user->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($user->two_factor_confirmed_at)->not->toBeNull();
});

it('stores a real secret that accepts the current TOTP code', function (): void {
    $user = createUserWithTwoFactor();

    expect(resolve(TwoFactorCodeVerifier::class)->verifyTotp($user, validOtpFor($user)))->toBeTrue();
});

it('stores eight recovery codes in the format Fortify generates', function (): void {
    $recoveryCodes = createUserWithTwoFactor()->recoveryCodes();

    expect($recoveryCodes)->toHaveCount(8)
        ->and($recoveryCodes)->each->toMatch(FORTIFY_RECOVERY_CODE_PATTERN);
});

it('creates a user with a pending two-factor secret that is not confirmed', function (): void {
    $user = createUserWithPendingTwoFactorState();

    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

it('stores recovery codes for a pending user, as Fortify does when enabling two-factor', function (): void {
    expect(createUserWithPendingTwoFactorState()->recoveryCodes())->toHaveCount(8);
});
