<?php

namespace Database\Factories;

use App\Like\Models\Like;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Like>
 */
class LikeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Like>
     */
    protected $model = Like::class;

    /**
     * Define the model's default state.
     *
     * @phpstan-return array<model-property<Like>, mixed>
     */
    public function definition(): array
    {
        return [
            //
        ];
    }
}
