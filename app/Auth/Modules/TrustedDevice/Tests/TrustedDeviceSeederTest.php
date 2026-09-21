<?php

declare(strict_types=1);

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\Auth\Seeders\TrustedDeviceSeeder;

it('seeds trusted devices and creation events for every user', function (): void {
    $user = createUser();

    $this->seed(TrustedDeviceSeeder::class);

    expect(TrustedDevice::query()->where('user_id', $user->id)->count())
        ->toBe(3)
        ->and(TrustedDeviceEvent::query()
            ->where('user_id', $user->id)
            ->where('action', TrustedDeviceAction::Created)
            ->count())
        ->toBe(3)
        ->and(TrustedDeviceEvent::query()
            ->where('user_id', $user->id)
            ->whereNotNull('device_os_name')
            ->count())
        ->toBe(3);
});
