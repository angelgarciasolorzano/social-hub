<?php

declare(strict_types=1);

use App\Auth\Modules\TrustedDevice\Policies\TrustedDevicePolicy;
use Illuminate\Support\Facades\Gate;

it('resolves the TrustedDevicePolicy from the model attribute', function (): void {
    $trustedDevice = createTrustedDevice();

    expect(Gate::getPolicyFor($trustedDevice))->toBeInstanceOf(TrustedDevicePolicy::class);
});

it('allows the owner to :dataset', function (string $ability): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user);

    expect(Gate::forUser($user)->allows($ability, $trustedDevice))->toBeTrue();
})->with(['update', 'delete', 'restore', 'forceDelete']);

it('denies another user to :dataset', function (string $ability): void {
    $trustedDevice = createTrustedDevice(createUser());
    $user = createUser();

    expect(Gate::forUser($user)->allows($ability, $trustedDevice))->toBeFalse();
})->with(['update', 'delete', 'restore', 'forceDelete']);

it('keeps authorizing restore and forceDelete by owner for a soft deleted device', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user);
    $trustedDevice->delete();

    $intruder = createUser();

    expect(Gate::forUser($user)->allows('restore', $trustedDevice))->toBeTrue()
        ->and(Gate::forUser($user)->allows('forceDelete', $trustedDevice))->toBeTrue()
        ->and(Gate::forUser($intruder)->allows('restore', $trustedDevice))->toBeFalse()
        ->and(Gate::forUser($intruder)->allows('forceDelete', $trustedDevice))->toBeFalse();
});
