<?php

declare(strict_types=1);

namespace App\Modules\Post\Database\Factories;

use App\Modules\Post\Models\Post;
use App\Support\Seeding\WeightedRandom;
use App\User\Database\Factories\UserFactory;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Post>
     */
    #[Override]
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, string|UserFactory>
     *
     * @phpstan-return array<model-property<Post>, mixed>
     */
    public function definition(): array
    {
        return [
            'content' => fake()->paragraph(random_int(1, 50)),
            'user_id' => User::factory(),
        ];
    }

    /**
     * Attach a random seeding image to each created post with the given probability (0 to 100).
     * Opt-in, so tests never copy seeding images unless they ask for them.
     */
    public function withImage(int $chancePercent = 100): static
    {
        return $this->afterCreating(function (Post $post) use ($chancePercent): void {
            if (WeightedRandom::chance($chancePercent)) {
                $images = glob(app_path(Post::TEST_IMAGES_GLOB_PATH), GLOB_BRACE);

                if (is_array($images) && $images !== []) {
                    $randomImage = $images[array_rand($images)];

                    $mediaAdder = $post->addMedia($randomImage);

                    if (app()->environment(['local', 'testing'])) {
                        $mediaAdder->preservingOriginal();
                    }

                    $mediaAdder->toMediaCollection(Post::POSTS_IMAGES_MEDIA_COLLECTION);
                }
            }
        });
    }
}
