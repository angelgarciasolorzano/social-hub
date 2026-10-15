<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\User\Models\User;
use Illuminate\Http\Request;

const BYPASS_COOKIE_TOKEN = 'known-trusted-device-token';

/**
 * @param  array<string, mixed>  $attributes
 */
function createBypassDevice(User $user, array $attributes = []): TrustedDevice
{
    return createTrustedDevice($user, [
        'token_hash' => hash('sha256', BYPASS_COOKIE_TOKEN),
        'last_used_at' => now()->subDay(),
        ...$attributes,
    ]);
}

function loginRequestWithTrustedCookie(string $token = BYPASS_COOKIE_TOKEN): Request
{
    return Request::create('/', 'POST', cookies: ['trusted_device' => $token]);
}

/**
 * @return array{email: string, password: string}
 */
function loginCredentialsFor(User $user): array
{
    return ['email' => $user->email, 'password' => 'password'];
}

it('skips the OTP challenge with a valid cookie when two-factor is confirmed', function (): void {
    $user = createUserWithTwoFactor();
    createBypassDevice($user);

    $this->withCookie('trusted_device', BYPASS_COOKIE_TOKEN)
        ->post(route('login.store'), loginCredentialsFor($user))
        ->assertRedirect(route('home', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('refreshes last_used_at when the cookie bypasses the OTP challenge', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createBypassDevice($user);

    $this->withCookie('trusted_device', BYPASS_COOKIE_TOKEN)
        ->post(route('login.store'), loginCredentialsFor($user));

    expect($trustedDevice->refresh()->last_used_at?->isToday())->toBeTrue();
});

it('does not skip the OTP challenge when the cookie is missing', function (): void {
    $user = createUserWithTwoFactor();
    createBypassDevice($user);

    $this->post(route('login.store'), loginCredentialsFor($user))
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

it('does not skip the OTP challenge with an expired cookie', function (): void {
    $user = createUserWithTwoFactor();
    createBypassDevice($user, ['expires_at' => now()->subMinute()]);

    $this->withCookie('trusted_device', BYPASS_COOKIE_TOKEN)
        ->post(route('login.store'), loginCredentialsFor($user))
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

it('does not skip the OTP challenge with a revoked cookie', function (): void {
    $user = createUserWithTwoFactor();
    createBypassDevice($user)->delete();

    $this->withCookie('trusted_device', BYPASS_COOKIE_TOKEN)
        ->post(route('login.store'), loginCredentialsFor($user))
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

it('does not skip the OTP challenge with a cookie that belongs to another user', function (): void {
    $user = createUserWithTwoFactor();
    createBypassDevice(createUserWithTwoFactor());

    $this->withCookie('trusted_device', BYPASS_COOKIE_TOKEN)
        ->post(route('login.store'), loginCredentialsFor($user))
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

it('does not trust a request only because its user agent and IP match a device', function (): void {
    $user = createUserWithTwoFactor();
    $fingerprint = trustedDeviceFingerprint();
    createBypassDevice($user, [
        'user_agent' => $fingerprint['user_agent'],
        'os_name' => $fingerprint['os_name'],
        'ip' => $fingerprint['ip'],
    ]);

    $this->withCookie('trusted_device', 'a-different-token')
        ->withServerVariables([
            'REMOTE_ADDR' => $fingerprint['ip'],
            'HTTP_USER_AGENT' => $fingerprint['user_agent'],
        ])
        ->post(route('login.store'), loginCredentialsFor($user))
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

it('still trusts a valid cookie when the user agent and IP change', function (): void {
    $user = createUserWithTwoFactor();
    createBypassDevice($user);

    $this->withCookie('trusted_device', BYPASS_COOKIE_TOKEN)
        ->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.99',
            'HTTP_USER_AGENT' => 'Completely/Different UserAgent',
        ])
        ->post(route('login.store'), loginCredentialsFor($user))
        ->assertRedirect(route('home', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('does not validate a cookie while the two-factor secret is pending confirmation', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createBypassDevice($user);
    $user->forceFill(['two_factor_confirmed_at' => null])->save();

    expect(TrustedDevice::validateAndTouch($user, loginRequestWithTrustedCookie()))->toBeFalse()
        ->and($trustedDevice->refresh()->last_used_at?->isToday())->toBeFalse();
});

it('does not validate a cookie when two-factor is disabled', function (): void {
    $user = createUser();
    $trustedDevice = createBypassDevice($user);

    expect(TrustedDevice::validateAndTouch($user, loginRequestWithTrustedCookie()))->toBeFalse()
        ->and($trustedDevice->refresh()->last_used_at?->isToday())->toBeFalse();
});
