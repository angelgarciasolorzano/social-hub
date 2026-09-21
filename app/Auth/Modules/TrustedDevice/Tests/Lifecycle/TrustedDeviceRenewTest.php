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

it('extends the expiration and records a Renewed event', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user, ['expires_at' => Date::now()->addDay()]);

    $testResponse = $this->actingAs($user)
        ->post(route('user.trusted-devices.renew', $trustedDevice));

    $testResponse->assertRedirect()
        ->assertInertiaFlash('type', 'success');

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

it('forbids renewing a device that belongs to another user', function (): void {
    $user = createUser();
    $expiresAt = Date::now()->addDay();
    $trustedDevice = createTrustedDevice($user, ['expires_at' => $expiresAt]);

    $intruder = createUser();

    $this->actingAs($intruder)
        ->post(route('user.trusted-devices.renew', $trustedDevice))
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
