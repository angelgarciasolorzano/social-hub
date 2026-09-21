<?php

declare(strict_types=1);

namespace App\Auth\Seeders;

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;

final class TrustedDeviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->get()->each(function (User $user): void {
            /** @var Factory<TrustedDevice> $trustedDeviceFactory */
            $trustedDeviceFactory = TrustedDevice::factory();

            $trustedDevices = $trustedDeviceFactory
                ->count(3)
                ->create(['user_id' => $user->id]);

            $trustedDevices->each(function (TrustedDevice $trustedDevice) use ($user): void {
                /** @var Factory<TrustedDeviceEvent> $trustedDeviceEventFactory */
                $trustedDeviceEventFactory = TrustedDeviceEvent::factory();

                $trustedDeviceEventFactory->create([
                    'user_id' => $user->id,
                    'action' => TrustedDeviceAction::Created,
                    'device_label' => $trustedDevice->name,
                    'device_is_mobile' => $trustedDevice->is_mobile,
                    'device_os_name' => $trustedDevice->os_name,
                    'ip' => $trustedDevice->ip,
                    'user_agent' => $trustedDevice->user_agent,
                ]);
            });
        });
    }
}
