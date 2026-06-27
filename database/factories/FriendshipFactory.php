<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Friendship\Models\Friendship;
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
     * Define the model's default state.
     *
     * @return array{}
     *
     * @phpstan-return array<model-property<Friendship>, mixed>
     */
    public function definition(): array
    {
        return [
            //
        ];
    }
}
