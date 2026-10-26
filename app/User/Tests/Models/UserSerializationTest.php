<?php

declare(strict_types=1);

use App\User\Models\User;

it('omits authentication secrets from array and json serialization', function (): void {
    $user = new User;
    $user->forceFill([
        'password' => 'password-secret',
        'remember_token' => 'remember-token-secret',
        'two_factor_secret' => 'totp-secret',
        'two_factor_recovery_codes' => 'recovery-codes-secret',
    ]);

    $hiddenAttributes = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /** @var array<string, mixed> $jsonRepresentation */
    $jsonRepresentation = json_decode($user->toJson(), true, flags: JSON_THROW_ON_ERROR);

    expect(array_intersect($hiddenAttributes, array_keys($user->toArray())))->toBeEmpty()
        ->and(array_intersect($hiddenAttributes, array_keys($jsonRepresentation)))->toBeEmpty();
});
