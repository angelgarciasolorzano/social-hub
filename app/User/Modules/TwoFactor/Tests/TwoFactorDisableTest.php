<?php

declare(strict_types=1);

use App\User\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

const DISABLE_TEST_RECOVERY_CODE = 'abcde12345-fghij67890';

function twoFactorManagementIsUnavailable(): bool
{
    return ! Features::canManageTwoFactorAuthentication();
}

/**
 * Ask to disable two-factor authentication as the given user, coming from the settings page.
 *
 * @param  array<string, string>  $data
 * @return TestResponse<Response>
 */
function disableTwoFactorAs(TestCase $testCase, User $user, array $data): TestResponse
{
    return $testCase->actingAs($user)
        ->from(route('setting.security.two-factor-authentication.index'))
        ->delete(route('setting.security.two-factor-authentication.destroy'), $data);
}

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

it('disables two-factor authentication with the current password and a valid recovery code', function (): void {
    $user = createUserWithRecoveryCodes(DISABLE_TEST_RECOVERY_CODE);

    disableTwoFactorAs($this, $user, ['password' => 'password', 'code' => DISABLE_TEST_RECOVERY_CODE])
        ->assertRedirectBackWithoutErrors();

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();
})->skip(twoFactorManagementIsUnavailable(...), 'Two-factor authentication is not enabled.');

it('revokes the trusted devices when two-factor authentication is disabled with a recovery code', function (): void {
    $user = createUserWithRecoveryCodes(DISABLE_TEST_RECOVERY_CODE);
    $trustedDevice = createTrustedDevice($user);

    disableTwoFactorAs($this, $user, ['password' => 'password', 'code' => DISABLE_TEST_RECOVERY_CODE]);

    expect($trustedDevice->fresh()?->deleted_at)->not->toBeNull();
})->skip(twoFactorManagementIsUnavailable(...), 'Two-factor authentication is not enabled.');

it('keeps two-factor authentication enabled when the recovery code is unknown', function (): void {
    $user = createUserWithRecoveryCodes(DISABLE_TEST_RECOVERY_CODE);

    disableTwoFactorAs($this, $user, ['password' => 'password', 'code' => 'zzzzz00000-yyyyy11111'])
        ->assertRedirectBackWithErrors(['code' => 'El código de autenticación proporcionado no es válido.']);

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($user->recoveryCodes())->toBe([DISABLE_TEST_RECOVERY_CODE]);
})->skip(twoFactorManagementIsUnavailable(...), 'Two-factor authentication is not enabled.');

it('keeps two-factor authentication enabled when the recovery code belongs to another user', function (): void {
    $owner = createUserWithRecoveryCodes(DISABLE_TEST_RECOVERY_CODE);
    $user = createUserWithTwoFactor();

    disableTwoFactorAs($this, $user, ['password' => 'password', 'code' => DISABLE_TEST_RECOVERY_CODE])
        ->assertRedirectBackWithErrors(['code' => 'El código de autenticación proporcionado no es válido.']);

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($owner->refresh()->recoveryCodes())->toBe([DISABLE_TEST_RECOVERY_CODE]);
})->skip(twoFactorManagementIsUnavailable(...), 'Two-factor authentication is not enabled.');

it('does not consume the recovery code when the current password is incorrect', function (): void {
    $user = createUserWithRecoveryCodes(DISABLE_TEST_RECOVERY_CODE);

    disableTwoFactorAs($this, $user, ['password' => 'incorrect-password', 'code' => DISABLE_TEST_RECOVERY_CODE])
        ->assertRedirectBackWithErrors(['password' => 'La contraseña proporcionada no coincide con tu contraseña actual.']);

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($user->recoveryCodes())->toBe([DISABLE_TEST_RECOVERY_CODE]);
})->skip(twoFactorManagementIsUnavailable(...), 'Two-factor authentication is not enabled.');

it('rejects a code that is neither six digits nor a recovery code', function (string $malformedCode): void {
    $user = createUserWithTwoFactor();

    disableTwoFactorAs($this, $user, ['password' => 'password', 'code' => $malformedCode])
        ->assertSessionHasErrors('code');

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
})->with([
    'letters' => ['abcdef'],
    'too short' => ['123'],
    'recovery code without the hyphen' => ['abcde12345fghij67890'],
])->skip(twoFactorManagementIsUnavailable(...), 'Two-factor authentication is not enabled.');
