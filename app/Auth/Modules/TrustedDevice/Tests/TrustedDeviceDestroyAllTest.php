<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;

it('redirects guests to the login page', function (): void {
    $this->delete(route('user.trusted-devices.destroy-all'), [
        'password' => 'password',
        'terms' => true,
    ])->assertRedirect(route('login'));
});

it('revokes every active device and records a RevokedAll event for each', function (): void {
    $user = createUser();
    $devices = collect([
        createTrustedDevice($user),
        createTrustedDevice($user),
        createTrustedDevice($user),
    ]);

    $testResponse = $this->actingAs($user)
        ->delete(route('user.trusted-devices.destroy-all'), [
            'password' => 'password',
            'terms' => true,
        ]);

    $testResponse->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    expect(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and(TrustedDevice::withTrashed()->where('user_id', $user->id)->count())->toBe(3)
        ->and(TrustedDeviceEvent::query()
            ->where('user_id', $user->id)
            ->where('action', TrustedDeviceAction::RevokedAll)
            ->whereIn('trusted_device_id', $devices->pluck('id'))
            ->count())->toBe(3);
});

it("does not touch another user's devices", function (): void {
    $user = createUser();
    createTrustedDevice($user);

    $otherUser = createUser();
    $trustedDevice = createTrustedDevice($otherUser);

    $this->actingAs($user)
        ->delete(route('user.trusted-devices.destroy-all'), [
            'password' => 'password',
            'terms' => true,
        ]);

    expect($trustedDevice->fresh()?->deleted_at)->toBeNull();
});

it('does not re-revoke an already revoked device', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user);
    $trustedDevice->delete();

    $activeDevice = createTrustedDevice($user);

    $this->actingAs($user)
        ->delete(route('user.trusted-devices.destroy-all'), [
            'password' => 'password',
            'terms' => true,
        ]);

    expect(TrustedDeviceEvent::query()
        ->where('trusted_device_id', $trustedDevice->id)
        ->count())->toBe(0)
        ->and(TrustedDeviceEvent::query()
            ->where('trusted_device_id', $activeDevice->id)
            ->where('action', TrustedDeviceAction::RevokedAll)
            ->exists())->toBeTrue();
});

it('rejects an incorrect password', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user);

    $testResponse = $this->actingAs($user)
        ->delete(route('user.trusted-devices.destroy-all'), [
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
        ->delete(route('user.trusted-devices.destroy-all'), [
            'password' => 'password',
            'terms' => false,
        ]);

    $testResponse->assertSessionHasErrors('terms');

    expect($trustedDevice->fresh()?->deleted_at)->toBeNull();
});
