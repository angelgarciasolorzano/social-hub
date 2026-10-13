<?php

declare(strict_types=1);

namespace App\Modules\Comment\Database\Factories;

use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
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
     * @return array<string, mixed>
     *
     * @phpstan-return array<model-property<Comment>, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'replied_to_user_id' => null,
            'commentable_id' => Post::factory(),
            'commentable_type' => Post::MORPH_NAME,
            'content' => fake()->paragraph(random_int(1, 30)),
        ];
    }

    /**
     * Mark the comment as a reply that mentions the given user.
     */
    public function repliedTo(User $user): static
    {
        return $this->state(fn (): array => ['replied_to_user_id' => $user->id]);
    }
}
