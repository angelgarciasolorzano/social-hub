<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Laravel\Fortify\Features;

it('regenerates recovery codes and updates their timestamp with the current password', function (): void {
    $user = createUserWithTwoFactor();
    $previousRecoveryCodes = $user->recoveryCodes();
    $previousRegenerationTimestamp = CarbonImmutable::parse('2026-09-30 12:00:00');
    $expectedRegenerationTimestamp = CarbonImmutable::parse('2026-10-01 12:00:00');

    $this->travelTo($expectedRegenerationTimestamp);

    $user->forceFill([
        'recovery_codes_regenerated_at' => $previousRegenerationTimestamp,
    ])->save();

    $this->actingAs($user)
        ->from(route('setting.security.two-factor-authentication.index'))
        ->post(route('setting.security.two-factor-authentication.store-recovery-codes'), [
            'password' => 'password',
        ])
        ->assertRedirectBackWithoutErrors();

    $regeneratedUser = $user->refresh();
    $regeneratedRecoveryCodes = $regeneratedUser->recoveryCodes();

    expect($regeneratedRecoveryCodes)->not->toEqual($previousRecoveryCodes)
        ->toHaveCount(8)
        ->and($regeneratedUser->recovery_codes_regenerated_at?->equalTo($expectedRegenerationTimestamp))
        ->toBeTrue();
})->skip(
    fn (): bool => ! Features::canManageTwoFactorAuthentication(),
    'Two-factor authentication is not enabled.'
);

it('preserves recovery codes and their timestamp when the current password is incorrect', function (): void {
    $user = createUserWithTwoFactor();
    $previousRecoveryCodes = $user->recoveryCodes();
    $previousRegenerationTimestamp = CarbonImmutable::parse('2026-09-30 12:00:00');

    $user->forceFill([
        'recovery_codes_regenerated_at' => $previousRegenerationTimestamp,
    ])->save();

    $this->actingAs($user)
        ->from(route('setting.security.two-factor-authentication.index'))
        ->post(route('setting.security.two-factor-authentication.store-recovery-codes'), [
            'password' => 'incorrect-password',
        ])
        ->assertRedirectBackWithErrors([
            'password' => 'La contraseña proporcionada no es correcta.',
        ]);

    $unchangedUser = $user->refresh();

    expect($unchangedUser->recoveryCodes())->toEqual($previousRecoveryCodes)
        ->and($unchangedUser->recovery_codes_regenerated_at?->equalTo($previousRegenerationTimestamp))
        ->toBeTrue();
})->skip(
    fn (): bool => ! Features::canManageTwoFactorAuthentication(),
    'Two-factor authentication is not enabled.'
);
