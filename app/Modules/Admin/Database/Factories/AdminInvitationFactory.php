<?php

declare(strict_types=1);

namespace App\Modules\Admin\Database\Factories;

use App\Modules\Admin\Models\AdminInvitation;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<AdminInvitation>
 */
class AdminInvitationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<AdminInvitation>
     */
    #[Override]
    protected $model = AdminInvitation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     *
     * @phpstan-return array<model-property<AdminInvitation>, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'invited_by_user_id' => User::factory(),
            'target_user_id' => null,
            'token_hash' => hash('sha256', fake()->uuid()),
            'expires_at' => CarbonImmutable::now()->addDays(7),
            'accepted_at' => null,
        ];
    }
}
