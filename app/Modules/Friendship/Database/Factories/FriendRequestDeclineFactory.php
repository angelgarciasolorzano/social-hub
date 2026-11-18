<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Database\Factories;

use App\Modules\Friendship\Models\FriendRequestDecline;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<FriendRequestDecline>
 */
class FriendRequestDeclineFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<FriendRequestDecline>
     */
    #[Override]
    protected $model = FriendRequestDecline::class;

    /**
     * Define the model's default state: a request between two new users, declined just now.
     *
     * @return array<string, mixed>
     *
     * @phpstan-return array<model-property<FriendRequestDecline>, mixed>
     */
    public function definition(): array
    {
        return [
            'requester_id' => User::factory(),
            'receiver_id' => User::factory(),
            'declined_at' => now(),
        ];
    }

    /**
     * The receiver declined the requester's request.
     */
    public function between(User $requester, User $receiver): static
    {
        return $this->state([
            'requester_id' => $requester->id,
            'receiver_id' => $receiver->id,
        ]);
    }

    /**
     * The request was declined the given number of days ago.
     */
    public function declinedDaysAgo(int $days): static
    {
        return $this->state(fn (): array => [
            'declined_at' => now()->subDays($days),
        ]);
    }
}
