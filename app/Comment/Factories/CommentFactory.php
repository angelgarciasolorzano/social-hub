<?php

declare(strict_types=1);

namespace App\Comment\Factories;

use App\Comment\Models\Comment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Comment>
     */
    #[Override]
    protected $model = Comment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, string|null>
     *
     * @phpstan-return array<model-property<Comment>, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'commentable_id' => null,
            'commentable_type' => null,
            'content' => fake()->paragraph(random_int(1, 30)),
        ];
    }
}
