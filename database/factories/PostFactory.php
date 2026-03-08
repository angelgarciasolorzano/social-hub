<?php

namespace Database\Factories;

use App\Post\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Post>
     */
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @phpstan-return array<model-property<Post>, mixed>
     */
    public function definition(): array
    {
        return [
            //
        ];
    }
}
