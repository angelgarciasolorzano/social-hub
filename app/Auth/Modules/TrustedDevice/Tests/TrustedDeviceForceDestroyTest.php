<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;

it('redirects guests to the login page', function (): void {
    $trustedDevice = createTrustedDevice();
    $trustedDevice->delete();

    $this->delete(route('user.trusted-devices.force-destroy', $trustedDevice), [
        'password' => 'password',
        'terms' => true,
    ])->assertRedirect(route('login'));
});

it('permanently deletes a revoked device and records a final Revoked event', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user, ['name' => 'Old phone']);
    $trustedDevice->delete();

    $testResponse = $this->actingAs($user)
        ->delete(route('user.trusted-devices.force-destroy', $trustedDevice), [
            'password' => 'password',
            'terms' => true,
        ]);

    $testResponse->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    expect(TrustedDevice::withTrashed()->find($trustedDevice->id))->toBeNull()
        ->and(TrustedDeviceEvent::query()
            ->where('user_id', $user->id)
            ->where('device_label', 'Old phone')
            ->where('action', TrustedDeviceAction::Revoked)
            ->exists())->toBeTrue();
});

it('returns not found for a device that is not revoked', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user);

    $this->actingAs($user)
        ->delete(route('user.trusted-devices.force-destroy', $trustedDevice), [
            'password' => 'password',
            'terms' => true,
        ])
        ->assertNotFound();

    expect(TrustedDevice::query()->whereKey($trustedDevice->id)->exists())->toBeTrue();
});

it('forbids permanently deleting a device that belongs to another user', function (): void {
    $owner = createUser();
    $trustedDevice = createTrustedDevice($owner);
    $trustedDevice->delete();

    $intruder = createUser();

    $this->actingAs($intruder)
        ->delete(route('user.trusted-devices.force-destroy', $trustedDevice), [
            'password' => 'password',
            'terms' => true,
        ])
        ->assertForbidden();

    expect(TrustedDevice::withTrashed()->find($trustedDevice->id))->not->toBeNull();
});

it('rejects an incorrect password', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user);
    $trustedDevice->delete();

    $testResponse = $this->actingAs($user)
        ->delete(route('user.trusted-devices.force-destroy', $trustedDevice), [
            'password' => 'wrong-password',
            'terms' => true,
        ]);

    $testResponse->assertSessionHasErrors('password');

    expect(TrustedDevice::withTrashed()->find($trustedDevice->id))->not->toBeNull();
});

it('requires the terms to be accepted', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user);
    $trustedDevice->delete();

    $testResponse = $this->actingAs($user)
        ->delete(route('user.trusted-devices.force-destroy', $trustedDevice), [
            'password' => 'password',
            'terms' => false,
        ]);

    $testResponse->assertSessionHasErrors('terms');

    expect(TrustedDevice::withTrashed()->find($trustedDevice->id))->not->toBeNull();
});

it('returns not found for a nonexistent device', function (): void {
    $user = createUser();

    $this->actingAs($user)
        ->delete(route('user.trusted-devices.force-destroy', ['trustedDevice' => 999_999]), [
            'password' => 'password',
            'terms' => true,
        ])
        ->assertNotFound();
});
