<?php

declare(strict_types=1);

namespace App\Auth\Database\Factories;

use App\Auth\Models\TrustedDevice;
use App\Auth\Models\TrustedDeviceEvent;
use App\Auth\Modules\TrustedDevice\Enums\TrustedDeviceAction;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrustedDeviceEvent>
 */
class TrustedDeviceEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     *
     * @phpstan-return array<model-property<TrustedDeviceEvent>, mixed>
     */
    public function definition(): array
    {
        return [
            'trusted_device_id' => TrustedDevice::factory(),
            'user_id' => User::factory(),
            'action' => fake()->randomElement(TrustedDeviceAction::cases()),
            'device_label' => fake()->words(2, true),
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
