<?php

declare(strict_types=1);

use App\User\Database\Factories\UserFactory;
use App\User\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Confirm the pending two-factor setup of the given user with the given code.
 *
 * @return TestResponse<Response>
 */
function confirmTwoFactorForActivityLog(TestCase $testCase, User $user, string $code): TestResponse
{
    return $testCase->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
        ->post(route('two-factor.confirm'), ['code' => $code]);
}

/**
 * Ask to disable two-factor authentication as the given user with the given code.
 *
 * @return TestResponse<Response>
 */
function disableTwoFactorForActivityLog(TestCase $testCase, User $user, string $code): TestResponse
{
    return $testCase->actingAs($user)
        ->from(route('setting.security.two-factor-authentication.index'))
        ->delete(route('setting.security.two-factor-authentication.destroy'), [
            'password' => 'password',
            'code' => $code,
        ]);
}

/**
 * Number of `auth` activities recorded for the given event on the user's own account.
 */
function countOwnTwoFactorActivities(User $user, string $eventName): int
{
    return Activity::query()
        ->where('log_name', 'auth')
        ->where('event', $eventName)
        ->where('subject_type', 'user')
        ->where('subject_id', $user->id)
        ->where('causer_type', 'user')
        ->where('causer_id', $user->id)
        ->count();
}

it('records the confirmation of two-factor authentication', function (): void {
    $user = UserFactory::new()->twoFactorPending()->createOne();

    confirmTwoFactorForActivityLog($this, $user, validOtpFor($user))->assertRedirect();

    expect(countOwnTwoFactorActivities($user, 'auth.two_factor_confirmed'))->toBe(1);
})->skip(fn (): bool => ! Features::canManageTwoFactorAuthentication(), 'Two-factor authentication is not enabled.');

it('does not record a confirmation with an invalid code', function (): void {
    $user = UserFactory::new()->twoFactorPending()->createOne();

    confirmTwoFactorForActivityLog($this, $user, invalidOtpFor($user));

    expect(countOwnTwoFactorActivities($user, 'auth.two_factor_confirmed'))->toBe(0);
})->skip(fn (): bool => ! Features::canManageTwoFactorAuthentication(), 'Two-factor authentication is not enabled.');

it('records disabling two-factor authentication', function (): void {
    $user = createUserWithTwoFactor();

    disableTwoFactorForActivityLog($this, $user, validOtpFor($user))->assertRedirectBackWithoutErrors();

    expect(countOwnTwoFactorActivities($user, 'auth.two_factor_disabled'))->toBe(1);
})->skip(fn (): bool => ! Features::canManageTwoFactorAuthentication(), 'Two-factor authentication is not enabled.');

it('does not record a disable attempt with an invalid code', function (): void {
    $user = createUserWithTwoFactor();

    disableTwoFactorForActivityLog($this, $user, invalidOtpFor($user))->assertRedirectBackWithErrors(['code']);

    expect(countOwnTwoFactorActivities($user, 'auth.two_factor_disabled'))->toBe(0);
})->skip(fn (): bool => ! Features::canManageTwoFactorAuthentication(), 'Two-factor authentication is not enabled.');

it('records the regeneration of recovery codes', function (): void {
    $user = createUserWithTwoFactor();

    $this->actingAs($user)
        ->from(route('setting.security.two-factor-authentication.index'))
        ->post(route('setting.security.two-factor-authentication.store-recovery-codes'), ['password' => 'password'])
        ->assertRedirectBackWithoutErrors();

    expect(countOwnTwoFactorActivities($user, 'auth.recovery_codes_regenerated'))->toBe(1);
})->skip(fn (): bool => ! Features::canManageTwoFactorAuthentication(), 'Two-factor authentication is not enabled.');

it('never stores the secret, the submitted code or the recovery codes', function (): void {
    $user = UserFactory::new()->twoFactorPending()->createOne();

    $otp = validOtpFor($user);

    /** @var string $encryptedSecret */
    $encryptedSecret = $user->two_factor_secret;

    /** @var string $secret */
    $secret = Fortify::currentEncrypter()->decrypt($encryptedSecret);

    /** @var list<string> $initialRecoveryCodes */
    $initialRecoveryCodes = $user->recoveryCodes();

    confirmTwoFactorForActivityLog($this, $user, $otp)->assertRedirect();

    $this->post(route('setting.security.two-factor-authentication.store-recovery-codes'), ['password' => 'password']);

    /** @var list<string> $regeneratedRecoveryCodes */
    $regeneratedRecoveryCodes = $user->refresh()->recoveryCodes();

    allowOtpReuse($otp);
    disableTwoFactorForActivityLog($this, $user, $otp)->assertRedirectBackWithoutErrors();

    $storedActivities = Activity::query()->get()->toJson();

    $leakedValues = array_filter(
        [$secret, $otp, ...$initialRecoveryCodes, ...$regeneratedRecoveryCodes],
        fn (string $sensitiveValue): bool => str_contains($storedActivities, $sensitiveValue),
    );

    expect(Activity::query()->where('log_name', 'auth')->count())->toBe(3)
        ->and($leakedValues)->toBeEmpty();
})->skip(fn (): bool => ! Features::canManageTwoFactorAuthentication(), 'Two-factor authentication is not enabled.');
