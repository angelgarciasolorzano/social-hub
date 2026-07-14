<?php

declare(strict_types=1);

namespace Database\Factories\Auth\TrustedDevice\Models;

use App\Auth\TrustedDevice\Models\TrustedDevice;
use App\User\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<TrustedDevice>
 */
class TrustedDeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, CarbonInterface|string>
     *
     * @phpstan-return array<model-property<TrustedDevice>, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token_hash' => hash('sha256', Str::random(64)),
            'name' => fake()->optional(0.6)->userAgent(),
            'user_agent' => fake()->userAgent(),
            'ip' => fake()->ipv4(),
            'last_used_at' => fake()->optional(0.5)->dateTimeBetween('-30 days', 'now'),
            'expires_at' => Carbon::now()->addDays(30),
        ];
    }
}
