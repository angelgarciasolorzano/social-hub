<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;

it('redirects guests to the login page', function (): void {
    $trustedDevice = createTrustedDevice();

    $this->patch(route('user.trusted-devices.update', $trustedDevice), ['name' => 'New name'])
        ->assertRedirect(route('login'));
});

it('renames the device and records a Renamed event', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user, ['name' => 'Old name']);

    $testResponse = $this->actingAs($user)
        ->patch(route('user.trusted-devices.update', $trustedDevice), ['name' => 'New name']);

    $testResponse->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    expect($trustedDevice->refresh()->name)->toBe('New name')
        ->and(TrustedDeviceEvent::query()
            ->where('user_id', $user->id)
            ->where('action', TrustedDeviceAction::Renamed)
            ->exists())
        ->toBeTrue();
});

it('forbids renaming a device that belongs to another user', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user, ['name' => 'Old name']);

    $intruder = createUser();

    $this->actingAs($intruder)
        ->patch(route('user.trusted-devices.update', $trustedDevice), ['name' => 'New name'])
        ->assertForbidden();

    expect($trustedDevice->refresh()->name)->toBe('Old name');
});

it('requires a name', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user, ['name' => 'Old name']);

    $testResponse = $this->actingAs($user)
        ->patch(route('user.trusted-devices.update', $trustedDevice), ['name' => '']);

    $testResponse->assertSessionHasErrors('name');

    expect($trustedDevice->refresh()->name)->toBe('Old name');
});

it('rejects a name longer than 50 characters', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user, ['name' => 'Old name']);

    $testResponse = $this->actingAs($user)
        ->patch(route('user.trusted-devices.update', $trustedDevice), ['name' => str_repeat('a', 51)]);

    $testResponse->assertSessionHasErrors('name');

    expect($trustedDevice->refresh()->name)->toBe('Old name');
});

it('returns not found for a nonexistent device', function (): void {
    $user = createUser();

    $this->actingAs($user)
        ->patch(route('user.trusted-devices.update', ['trustedDevice' => 999_999]), ['name' => 'New name'])
        ->assertNotFound();
});
