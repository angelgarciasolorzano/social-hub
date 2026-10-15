<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use Illuminate\Support\Facades\Date;

it('redirects guests to the login page', function (): void {
    $trustedDevice = createTrustedDevice();

    $this->post(route('user.trusted-devices.renew', $trustedDevice))
        ->assertRedirect(route('login'));
});

it('extends the expiration and records a Renewed event when the OTP is valid', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createTrustedDevice($user, ['expires_at' => Date::now()->addDay()]);

    $testResponse = $this->actingAs($user)
        ->post(route('user.trusted-devices.renew', $trustedDevice), ['otp_code' => validOtpFor($user)]);

    $testResponse->assertRedirect();
    $testResponse->assertInertiaFlash('type', 'success');

    /** @var int $cookieLifetimeMinutes */
    $cookieLifetimeMinutes = config('module.auth.trusted_devices.cookie_lifetime_minutes');

    expect($trustedDevice->refresh()->expires_at)
        ->toBeGreaterThan(Date::now()->addMinutes($cookieLifetimeMinutes - 1))
        ->and($trustedDevice->isActive())->toBeTrue()
        ->and(TrustedDeviceEvent::query()
            ->where('user_id', $user->id)
            ->where('action', TrustedDeviceAction::Renewed)
            ->exists())
        ->toBeTrue();
});

it('does not extend the trust when the OTP is missing, wrong or in recovery-code format', function (?string $otp): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createTrustedDevice($user, ['expires_at' => Date::now()->addDay()]);
    $expiresAt = $trustedDevice->refresh()->expires_at;

    $testResponse = $this->actingAs($user)
        ->post(route('user.trusted-devices.renew', $trustedDevice), $otp === null ? [] : ['otp_code' => $otp]);

    $testResponse->assertSessionHasErrors('otp_code');

    expect($trustedDevice->refresh()->expires_at->equalTo($expiresAt))->toBeTrue()
        ->and(TrustedDeviceEvent::query()->where('user_id', $user->id)->exists())->toBeFalse();
})->with([
    'missing code' => [null],
    'wrong code' => ['000000'],
    'recovery-format code' => ['abcde12345-fghij67890'],
]);

it('answers a wrong OTP code with a fixed message that does not echo the submitted code', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createTrustedDevice($user);

    $testResponse = $this->actingAs($user)
        ->post(route('user.trusted-devices.renew', $trustedDevice), ['otp_code' => '000000']);

    $testResponse->assertSessionHasErrors(['otp_code' => 'El código de verificación es incorrecto.']);
});

it('does not extend the trust when two-factor is disabled or pending', function (bool $hasPendingSecret): void {
    $user = $hasPendingSecret ? createUserWithTwoFactor() : createUser();

    if ($hasPendingSecret) {
        $user->forceFill(['two_factor_confirmed_at' => null])->save();
    }

    $trustedDevice = createTrustedDevice($user, ['expires_at' => Date::now()->addDay()]);
    $expiresAt = $trustedDevice->refresh()->expires_at;

    $testResponse = $this->actingAs($user)
        ->post(route('user.trusted-devices.renew', $trustedDevice), ['otp_code' => $hasPendingSecret ? validOtpFor($user) : '123456']);

    $testResponse->assertSessionHasErrors('two_factor');
    $testResponse->assertSessionDoesntHaveErrors('otp_code');

    expect($trustedDevice->refresh()->expires_at->equalTo($expiresAt))->toBeTrue()
        ->and(TrustedDeviceEvent::query()->where('user_id', $user->id)->exists())->toBeFalse();
})->with([
    'two-factor disabled' => [false],
    'pending secret' => [true],
]);

it('forbids renewing a device that belongs to another user', function (): void {
    $user = createUser();
    $expiresAt = Date::now()->addDay();
    $trustedDevice = createTrustedDevice($user, ['expires_at' => $expiresAt]);

    $intruder = createUserWithTwoFactor();

    $this->actingAs($intruder)
        ->post(route('user.trusted-devices.renew', $trustedDevice), ['otp_code' => validOtpFor($intruder)])
        ->assertForbidden();

    expect($trustedDevice->refresh()->expires_at->toDateTimeString())
        ->toBe($expiresAt->toDateTimeString());
});

it('returns not found for a nonexistent device', function (): void {
    $user = createUser();

    $this->actingAs($user)
        ->post(route('user.trusted-devices.renew', ['trustedDevice' => 999_999]))
        ->assertNotFound();
});

it('returns not found for a revoked device', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user);
    $trustedDevice->delete();

    $this->actingAs($user)
        ->post(route('user.trusted-devices.renew', $trustedDevice))
        ->assertNotFound();
});
