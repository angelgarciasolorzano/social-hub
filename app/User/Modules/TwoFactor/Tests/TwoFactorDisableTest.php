<?php

declare(strict_types=1);

use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use LogicException;
use PragmaRX\Google2FA\Google2FA;

it('disables two-factor authentication with the current password and a valid code', function (): void {
    $user = createUserWithTwoFactor();

    $this->actingAs($user)
        ->from(route('setting.security.two-factor-authentication.index'))
        ->delete(route('setting.security.two-factor-authentication.destroy'), [
            'password' => 'password',
            'code' => validOtpFor($user),
        ])
        ->assertRedirectBackWithoutErrors();

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();
})->skip(
    fn (): bool => ! Features::canManageTwoFactorAuthentication(),
    'Two-factor authentication is not enabled.'
);

it('keeps two-factor authentication enabled when the current password is incorrect', function (): void {
    $user = createUserWithTwoFactor();

    $this->actingAs($user)
        ->from(route('setting.security.two-factor-authentication.index'))
        ->delete(route('setting.security.two-factor-authentication.destroy'), [
            'password' => 'incorrect-password',
            'code' => validOtpFor($user),
        ])
        ->assertRedirectBackWithErrors([
            'password' => 'La contraseña proporcionada no coincide con tu contraseña actual.',
        ]);

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
})->skip(
    fn (): bool => ! Features::canManageTwoFactorAuthentication(),
    'Two-factor authentication is not enabled.'
);

it('keeps two-factor authentication enabled when the TOTP code is invalid', function (): void {
    $user = createUserWithTwoFactor();

    $encryptedTwoFactorSecret = $user->getAttribute('two_factor_secret');

    throw_unless(is_string($encryptedTwoFactorSecret), LogicException::class, 'The test user must have an encrypted two-factor secret.');

    $twoFactorSecret = Fortify::currentEncrypter()->decrypt($encryptedTwoFactorSecret);

    throw_unless(is_string($twoFactorSecret), LogicException::class, 'The test user two-factor secret must decrypt to a string.');

    /** @var Google2FA $google2fa */
    $google2fa = resolve(Google2FA::class);
    $twoFactorAuthenticationProvider = resolve(TwoFactorAuthenticationProvider::class);
    $historicalTimeStep = intdiv(1_600_000_000, $google2fa->getKeyRegeneration());
    $invalidCode = null;

    for ($candidateOffset = 0; $candidateOffset < 100; $candidateOffset++) {
        $candidateCode = $google2fa->oathTotp($twoFactorSecret, $historicalTimeStep - $candidateOffset);

        if (! $twoFactorAuthenticationProvider->verify($twoFactorSecret, $candidateCode)) {
            $invalidCode = $candidateCode;

            break;
        }
    }

    throw_unless(is_string($invalidCode), LogicException::class, 'Could not prepare an invalid TOTP code for the test.');

    $this->actingAs($user)
        ->from(route('setting.security.two-factor-authentication.index'))
        ->delete(route('setting.security.two-factor-authentication.destroy'), [
            'password' => 'password',
            'code' => $invalidCode,
        ])
        ->assertRedirectBackWithErrors([
            'code' => 'El código de autenticación proporcionado no es válido.',
        ]);

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
})->skip(
    fn (): bool => ! Features::canManageTwoFactorAuthentication(),
    'Two-factor authentication is not enabled.'
);
