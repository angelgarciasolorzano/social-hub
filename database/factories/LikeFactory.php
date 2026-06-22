<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Like\Models\Like;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

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
    #[Override]
    protected $model = Like::class;

    /**
     * Define the model's default state.
     *
     * @return array{}
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
