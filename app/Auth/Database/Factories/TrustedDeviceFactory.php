<?php

declare(strict_types=1);

namespace App\Auth\Database\Factories;

use App\Auth\Models\TrustedDevice;
use App\User\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use LogicException;

/**
 * @extends Factory<TrustedDevice>
 */
class TrustedDeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, CarbonInterface|string|bool>
     *
     * @phpstan-return array<model-property<TrustedDevice>, mixed>
     */
    public function definition(): array
    {
        $osName = fake()->randomElement(['Windows', 'Mac', 'Linux', 'iOS', 'Android']);

        return [
            'user_id' => User::factory(),
            'token_hash' => hash('sha256', Str::random(64)),
            'name' => fake()->optional(0.6)->userAgent(),
            'user_agent' => fake()->userAgent(),
            'browser' => fake()->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
            'browser_version' => (string) fake()->numberBetween(90, 140),
            'os_name' => $osName,
            'os_version' => match ($osName) {
                'Windows' => fake()->randomElement(['10', '11']),
                'Mac' => fake()->randomElement(['14', '15']),
                'Linux' => fake()->randomElement(['22.04', '24.04']),
                'iOS' => fake()->randomElement(['17', '18']),
                'Android' => fake()->randomElement(['14', '15']),
                default => throw new LogicException('Unsupported operating system selected by factory.'),
            },
            'is_mobile' => in_array($osName, ['iOS', 'Android'], true),
            'ip' => fake()->ipv4(),
            'last_used_at' => fake()->optional(0.5)->dateTimeBetween('-30 days', 'now'),
            'expires_at' => Date::now()->addDays(30),
        ];
    }
}
