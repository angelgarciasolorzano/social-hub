<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Database\Factories;

use App\Modules\Friendship\Enums\FriendshipStatus;
use App\Modules\Friendship\Models\Friendship;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Friendship>
 */
class FriendshipFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Friendship>
     */
    #[Override]
    protected $model = Friendship::class;

    /**
     * Define the model's default state: a pending request between two new users.
     *
     * @return array<string, mixed>
     *
     * @phpstan-return array<model-property<Friendship>, mixed>
     */
    public function definition(): array
    {
        return [
            'requester_id' => User::factory(),
            'receiver_id' => User::factory(),
            'status' => FriendshipStatus::Pending,
            'accepted_at' => null,
        ];
    }

    /**
     * A request still waiting for an answer.
     */
    public function pending(): static
    {
        return $this->state([
            'status' => FriendshipStatus::Pending,
            'accepted_at' => null,
        ]);
    }

    /**
     * A friendship the receiver accepted.
     */
    public function accepted(): static
    {
        return $this->state([
            'status' => FriendshipStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }

    /**
     * A row sent by the requester to the receiver.
     */
    public function between(User $requester, User $receiver): static
    {
        return $this->state([
            'requester_id' => $requester->id,
            'receiver_id' => $receiver->id,
        ]);
    }
}
