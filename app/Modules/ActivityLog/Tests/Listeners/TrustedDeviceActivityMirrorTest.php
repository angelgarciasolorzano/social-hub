<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

it('mirrors each recorded trusted device action', function (TrustedDeviceAction $trustedDeviceAction): void {
    $user = createUser();

    $trustedDeviceEvent = TrustedDeviceEvent::record(createTrustedDevice($user), $user, $trustedDeviceAction, trustedDeviceAuditRequest());

    $activity = Activity::query()->where('log_name', 'trusted_devices')->sole();

    expect($activity)
        ->event->toBe('trusted_devices.'.$trustedDeviceAction->value)
        ->subject_type->toBe('trusted_device_event')
        ->causer_type->toBe('user')
        ->causer_id->toBe($user->id)
        ->and($activity->subject?->is($trustedDeviceEvent))->toBeTrue();
})->with(TrustedDeviceAction::cases());

it('mirrors a trusted device action performed through the application', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user);

    $this->actingAs($user)
        ->delete(route('user.trusted-devices.destroy', $trustedDevice), ['password' => 'password', 'terms' => true])
        ->assertSessionHasNoErrors();

    expect(Activity::query()->where('event', 'trusted_devices.revoked')->sole()->causer_id)->toBe($user->id);
});

it('does not mirror trusted device events created by factories or seeders', function (): void {
    createTrustedDeviceEvent(createUser());

    expect(Activity::query()->where('log_name', 'trusted_devices')->exists())->toBeFalse();
});

it('discards the mirror together with the event when the transaction rolls back', function (): void {
    $user = createUser();
    $trustedDevice = createTrustedDevice($user);

    expect(fn () => DB::transaction(function () use ($trustedDevice, $user): void {
        TrustedDeviceEvent::record($trustedDevice, $user, TrustedDeviceAction::Revoked, trustedDeviceAuditRequest());

        throw new RuntimeException('The trusted device action failed after recording its event.');
    }))->toThrow(RuntimeException::class)
        ->and(TrustedDeviceEvent::query()
            ->exists())
        ->toBeFalse()
        ->and(Activity::query()
            ->exists())
        ->toBeFalse();
});
