<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceDashboardCache;
use App\Auth\Modules\TrustedDevice\Services\TrustedDeviceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

it('redirects guests to the login page', function (): void {
    $this->delete(route('user.trusted-devices.destroy-all'), [
        'password' => 'password',
        'terms' => true,
    ])->assertRedirect(route('login'));
});

it('revokes every active device and records a RevokedAll event for each', function (): void {
    $user = createUser();
    createTrustedDevice($user);
    createTrustedDevice($user);
    createTrustedDevice($user);

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
    createTrustedDevice($user)->delete();

    $trustedDevice = createTrustedDevice($user);

    $this->actingAs($user)
        ->delete(route('user.trusted-devices.destroy-all'), [
            'password' => 'password',
            'terms' => true,
        ]);

    expect(TrustedDeviceEvent::query()
        ->where('user_id', $user->id)
        ->where('action', TrustedDeviceAction::RevokedAll)
        ->count())->toBe(1)
        ->and($trustedDevice->fresh()?->deleted_at)->not->toBeNull();
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

it('invalidates the dashboard cache after revoking every device', function (): void {
    $user = createUser();
    createTrustedDevice($user);
    resolve(TrustedDeviceDashboardCache::class)->stats($user);

    $cacheKey = \sprintf('trusted-device:dashboard:%s:stats', $user->id);

    expect(Cache::has($cacheKey))->toBeTrue();

    $this->actingAs($user)
        ->delete(route('user.trusted-devices.destroy-all'), [
            'password' => 'password',
            'terms' => true,
        ]);

    expect(Cache::has($cacheKey))->toBeFalse();
});

it('succeeds without events or cache invalidation when the user has no devices', function (): void {
    $user = createUser();
    resolve(TrustedDeviceDashboardCache::class)->stats($user);

    $cacheKey = \sprintf('trusted-device:dashboard:%s:stats', $user->id);

    $testResponse = $this->actingAs($user)
        ->delete(route('user.trusted-devices.destroy-all'), [
            'password' => 'password',
            'terms' => true,
        ]);

    $testResponse->assertRedirect()
        ->assertInertiaFlash('type', 'success');

    expect(TrustedDeviceEvent::query()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(Cache::has($cacheKey))->toBeTrue();
});

it('returns how many devices the service revoked and leaves other users untouched', function (): void {
    $user = createUser();
    createTrustedDevice($user);
    createTrustedDevice($user);

    $otherUser = createUser();
    $trustedDevice = createTrustedDevice($otherUser);

    $revokedCount = resolve(TrustedDeviceService::class)->revokeAll($user, Request::create('/', 'DELETE'));

    expect($revokedCount)->toBe(2)
        ->and(resolve(TrustedDeviceService::class)->revokeAll($user, Request::create('/', 'DELETE')))->toBe(0)
        ->and($trustedDevice->fresh()?->deleted_at)->toBeNull();
});
