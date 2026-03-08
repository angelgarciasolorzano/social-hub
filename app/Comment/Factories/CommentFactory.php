<?php

namespace App\Comment\Factories;

use App\Comment\Models\Comment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Comment>
     */
    protected $model = Comment::class;

    /**
     * Define the model's default state.
     *
     * @phpstan-return array<model-property<Comment>, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'commentable_id' => null,
            'commentable_type' => null,
            'content' => fake()->paragraph(rand(1, 30)),
        ];
    }
}
