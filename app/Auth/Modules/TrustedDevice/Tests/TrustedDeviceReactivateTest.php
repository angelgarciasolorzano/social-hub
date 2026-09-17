<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;

it('redirects guests to the login page', function (): void {
    $trustedDevice = createTrustedDevice();
    $trustedDevice->delete();

    $this->post(route('user.trusted-devices.reactivate', $trustedDevice), ['otp_code' => '123456'])
        ->assertRedirect(route('login'));
});

it('reactivates a revoked device with a valid TOTP code', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createTrustedDevice($user);
    $trustedDevice->delete();

    $testResponse = $this->actingAs($user)
        ->post(route('user.trusted-devices.reactivate', $trustedDevice), [
            'otp_code' => validOtpFor($user),
        ]);

    $testResponse->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    $trustedDevice->refresh();

    expect($trustedDevice->deleted_at)->toBeNull()
        ->and($trustedDevice->isActive())->toBeTrue()
        ->and(TrustedDeviceEvent::query()
            ->where('trusted_device_id', $trustedDevice->id)
            ->where('user_id', $user->id)
            ->where('action', TrustedDeviceAction::Reactivated)
            ->exists())
        ->toBeTrue();
});

it('reactivates a revoked device with a valid recovery code', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createTrustedDevice($user);
    $trustedDevice->delete();

    $testResponse = $this->actingAs($user)
        ->post(route('user.trusted-devices.reactivate', $trustedDevice), [
            'otp_code' => '111111',
        ]);

    $testResponse->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    expect($trustedDevice->refresh()->deleted_at)->toBeNull();
});

it('rejects an incorrect otp code and keeps the device revoked', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createTrustedDevice($user);
    $trustedDevice->delete();

    $validOtp = validOtpFor($user);
    $wrongOtp = $validOtp === '000000' ? '000001' : '000000';

    $testResponse = $this->actingAs($user)
        ->post(route('user.trusted-devices.reactivate', $trustedDevice), [
            'otp_code' => $wrongOtp,
        ]);

    $testResponse->assertSessionHasErrors('otp_code');

    expect($trustedDevice->refresh()->deleted_at)->not->toBeNull();
});

it('rejects a malformed otp code', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createTrustedDevice($user);
    $trustedDevice->delete();

    $this->actingAs($user)
        ->post(route('user.trusted-devices.reactivate', $trustedDevice), ['otp_code' => 'abcdef'])
        ->assertSessionHasErrors('otp_code');
});

it('returns not found for a device that is not revoked', function (): void {
    $user = createUserWithTwoFactor();
    $trustedDevice = createTrustedDevice($user);

    $this->actingAs($user)
        ->post(route('user.trusted-devices.reactivate', $trustedDevice), [
            'otp_code' => validOtpFor($user),
        ])
        ->assertNotFound();
});

it('forbids reactivating a device that belongs to another user', function (): void {
    $owner = createUser();
    $trustedDevice = createTrustedDevice($owner);
    $trustedDevice->delete();

    $intruder = createUserWithTwoFactor();

    $this->actingAs($intruder)
        ->post(route('user.trusted-devices.reactivate', $trustedDevice), [
            'otp_code' => validOtpFor($intruder),
        ])
        ->assertForbidden();

    expect($trustedDevice->refresh()->deleted_at)->not->toBeNull();
});

it('drops an active duplicate with the same fingerprint and records it as Revoked', function (): void {
    $user = createUserWithTwoFactor();

    $fingerprint = [
        'user_agent' => chromeWindowsUserAgent(),
        'os_name' => 'Windows',
        'ip' => '203.0.113.5',
    ];

    $duplicate = createTrustedDevice($user, [...$fingerprint, 'name' => 'Duplicate device']);
    $trustedDevice = createTrustedDevice($user, $fingerprint);
    $trustedDevice->delete();

    $testResponse = $this->actingAs($user)
        ->post(route('user.trusted-devices.reactivate', $trustedDevice), [
            'otp_code' => validOtpFor($user),
        ]);

    $testResponse->assertInertiaFlash('type', 'success');

    expect(TrustedDevice::withTrashed()->find($duplicate->id))->toBeNull()
        ->and(TrustedDeviceEvent::query()
            ->where('user_id', $user->id)
            ->where('device_label', 'Duplicate device')
            ->where('action', TrustedDeviceAction::Revoked)
            ->exists())->toBeTrue()
        ->and($trustedDevice->refresh()->deleted_at)->toBeNull();
});
