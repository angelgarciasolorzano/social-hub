<?php

declare(strict_types=1);

namespace App\Modules\Like\Database\Factories;

use App\Modules\Like\Models\Like;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
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
     * @return array<string, mixed>
     *
     * @phpstan-return array<model-property<Like>, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'likeable_id' => Post::factory(),
            'likeable_type' => Post::MORPH_NAME,
        ];
    }
}
