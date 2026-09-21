<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;

it('redirects guests to the login page', function (): void {
    $trustedDevice = createTrustedDevice();

    $this->delete(route('user.trusted-devices.destroy', $trustedDevice), [
        'password' => 'password',
        'terms' => true,
    ])->assertRedirect(route('login'));
});

it('soft deletes the device and records a Revoked event', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user);

    $testResponse = $this->actingAs($user)
        ->delete(route('user.trusted-devices.destroy', $trustedDevice), [
            'password' => 'password',
            'terms' => true,
        ]);

    $testResponse->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    expect(TrustedDevice::withTrashed()->find($trustedDevice->id)?->deleted_at)->not->toBeNull()
        ->and(TrustedDevice::query()->whereKey($trustedDevice->id)->exists())->toBeFalse()
        ->and(TrustedDeviceEvent::query()
            ->where('user_id', $user->id)
            ->where('action', TrustedDeviceAction::Revoked)
            ->exists())
        ->toBeTrue();
});

it('forbids revoking a device that belongs to another user', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user);

    $intruder = createUser();

    $this->actingAs($intruder)
        ->delete(route('user.trusted-devices.destroy', $trustedDevice), [
            'password' => 'password',
            'terms' => true,
        ])
        ->assertForbidden();

    expect($trustedDevice->fresh()?->deleted_at)->toBeNull();
});

it('rejects an incorrect password', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user);

    $testResponse = $this->actingAs($user)
        ->delete(route('user.trusted-devices.destroy', $trustedDevice), [
            'password' => 'wrong-password',
            'terms' => true,
        ]);

    $testResponse->assertSessionHasErrors('password');

    expect($trustedDevice->fresh()?->deleted_at)->toBeNull();
});

it('requires the terms to be accepted', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user);

    $testResponse = $this->actingAs($user)
        ->delete(route('user.trusted-devices.destroy', $trustedDevice), [
            'password' => 'password',
            'terms' => false,
        ]);

    $testResponse->assertSessionHasErrors('terms');

    expect($trustedDevice->fresh()?->deleted_at)->toBeNull();
});

it('returns not found for a nonexistent device', function (): void {
    $user = createUser();

    $this->actingAs($user)
        ->delete(route('user.trusted-devices.destroy', ['trustedDevice' => 999_999]), [
            'password' => 'password',
            'terms' => true,
        ])
        ->assertNotFound();
});
