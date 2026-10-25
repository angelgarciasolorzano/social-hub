<?php

declare(strict_types=1);

namespace App\User\Database\Factories;

use App\User\Enums\AccountStatus;
use App\User\Models\AccountStatusChange;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountStatusChange>
 */
class AccountStatusChangeFactory extends Factory
{
    /**
     * Define the model's default state: a suspension made by another account.
     *
     * @return array<string, mixed>
     *
     * @phpstan-return array<model-property<AccountStatusChange>, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => AccountStatus::Suspended,
            'reason' => fake()->sentence(),
            'changed_by' => User::factory(),
        ];
    }

    /**
     * A change that suspended the account.
     */
    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => AccountStatus::Suspended,
        ]);
    }

    /**
     * A change that reactivated the account.
     */
    public function reactivated(): static
    {
        return $this->state(fn (): array => [
            'status' => AccountStatus::Active,
        ]);
    }

    /**
     * A change made by the given administrator.
     */
    public function changedBy(User $user): static
    {
        return $this->state(fn (): array => [
            'changed_by' => $user->id,
        ]);
    }
}
