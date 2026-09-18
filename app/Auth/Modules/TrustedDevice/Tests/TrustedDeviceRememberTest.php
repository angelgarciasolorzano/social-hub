<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;

it('creates a trusted device and records a Created event when remember_device is checked', function (): void {
    $user = createUserWithTwoFactor();

    $this->withSession(['login.id' => $user->id])
        ->withHeader('User-Agent', chromeWindowsUserAgent())
        ->post(route('two-factor.login.store'), [
            'code' => validOtpFor($user),
            'remember_device' => true,
        ])
        ->assertRedirect();

    $trustedDevice = TrustedDevice::query()->where('user_id', $user->id)->sole();

    expect($trustedDevice->user_agent)->toBe(chromeWindowsUserAgent())
        ->and($trustedDevice->isActive())->toBeTrue()
        ->and(TrustedDeviceEvent::query()
            ->where('trusted_device_id', $trustedDevice->id)
            ->where('user_id', $user->id)
            ->where('action', TrustedDeviceAction::Created)
            ->exists())->toBeTrue();
});

it('does not create a trusted device when remember_device is not checked', function (): void {
    $user = createUserWithTwoFactor();

    $this->withSession(['login.id' => $user->id])
        ->post(route('two-factor.login.store'), [
            'code' => validOtpFor($user),
        ])
        ->assertRedirect();

    expect(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(0);
});
