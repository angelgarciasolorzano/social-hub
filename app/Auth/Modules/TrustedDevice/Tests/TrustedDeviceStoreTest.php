<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;

it('redirects guests to the login page', function (): void {
    $this->post(route('user.trusted-devices.store'))
        ->assertRedirect(route('login'));
});

it('creates a trusted device with an inferred name when no name is given', function (): void {
    $user = createUser();

    $testResponse = $this->actingAs($user)
        ->withHeader('User-Agent', chromeWindowsUserAgent())
        ->post(route('user.trusted-devices.store'));

    $testResponse->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    $trustedDevice = TrustedDevice::query()->where('user_id', $user->id)->sole();

    expect($trustedDevice->name)->not->toBeEmpty()
        ->and($trustedDevice->user_agent)->toBe(chromeWindowsUserAgent())
        ->and($trustedDevice->os_name)->toBe('Windows')
        ->and($trustedDevice->is_mobile)->toBeFalse()
        ->and($trustedDevice->isActive())->toBeTrue()
        ->and(TrustedDeviceEvent::query()
            ->where('user_id', $user->id)
            ->where('action', TrustedDeviceAction::Created)
            ->exists())
        ->toBeTrue();
});

it('accepts a custom device name', function (): void {
    $user = createUser();

    $this->actingAs($user)
        ->withHeader('User-Agent', chromeWindowsUserAgent())
        ->post(route('user.trusted-devices.store'), ['name' => 'Mi laptop personal']);

    expect(TrustedDevice::query()->where('user_id', $user->id)->sole()->name)
        ->toBe('Mi laptop personal');
});

it('rejects a name longer than 50 characters', function (): void {
    $user = createUser();

    $testResponse = $this->actingAs($user)
        ->withHeader('User-Agent', chromeWindowsUserAgent())
        ->post(route('user.trusted-devices.store'), ['name' => str_repeat('a', 51)]);

    $testResponse->assertSessionHasErrors('name');

    expect(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('does not create a duplicate when an active device with the same fingerprint already exists', function (): void {
    $user = createUser();

    $this->actingAs($user)
        ->withHeader('User-Agent', chromeWindowsUserAgent())
        ->post(route('user.trusted-devices.store'));

    $testResponse = $this->actingAs($user)
        ->withHeader('User-Agent', chromeWindowsUserAgent())
        ->post(route('user.trusted-devices.store'));

    $testResponse->assertInertiaFlash('type', 'error');

    expect(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(TrustedDeviceEvent::query()
            ->where('user_id', $user->id)
            ->where('action', TrustedDeviceAction::Created)
            ->count())->toBe(1);
});

it('reports a revoked duplicate as needing reactivation instead of creating a new device', function (): void {
    $user = createUser();

    $this->actingAs($user)
        ->withHeader('User-Agent', chromeWindowsUserAgent())
        ->post(route('user.trusted-devices.store'));

    TrustedDevice::query()->where('user_id', $user->id)->sole()->delete();

    $testResponse = $this->actingAs($user)
        ->withHeader('User-Agent', chromeWindowsUserAgent())
        ->post(route('user.trusted-devices.store'));

    $testResponse->assertInertiaFlash('type', 'error');

    expect(TrustedDevice::withTrashed()->where('user_id', $user->id)->count())->toBe(1)
        ->and(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(0);
});
