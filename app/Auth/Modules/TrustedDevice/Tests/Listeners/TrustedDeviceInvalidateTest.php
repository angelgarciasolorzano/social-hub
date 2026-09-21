<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;

it('revokes every trusted device and records one global RevokedAll event when 2FA is disabled', function (): void {
    $user = createUserWithTwoFactor();
    createTrustedDevice($user);
    createTrustedDevice($user);

    $this->actingAs($user)
        ->delete(route('setting.security.two-factor-authentication.destroy'), [
            'password' => 'password',
            'code' => validOtpFor($user),
        ])
        ->assertRedirect();

    expect(TrustedDevice::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and(TrustedDevice::withTrashed()->where('user_id', $user->id)->count())->toBe(2)
        ->and(TrustedDeviceEvent::query()
            ->where('user_id', $user->id)
            ->where('action', TrustedDeviceAction::RevokedAll)
            ->count())->toBe(1);
});

it("does not touch another user's devices", function (): void {
    $user = createUserWithTwoFactor();

    $otherUser = createUser();
    $trustedDevice = createTrustedDevice($otherUser);

    $this->actingAs($user)
        ->delete(route('setting.security.two-factor-authentication.destroy'), [
            'password' => 'password',
            'code' => validOtpFor($user),
        ]);

    expect($trustedDevice->fresh()?->deleted_at)->toBeNull();
});
