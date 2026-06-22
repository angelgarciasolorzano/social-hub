<?php

declare(strict_types=1);

namespace App\Post\Factories;

use App\Post\Models\Post;
use App\User\Factories\UserFactory;
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
     * Configure the model factory.
     */
    #[Override]
    public function configure()
    {
        return $this->afterCreating(function (Post $post): void {
            if (random_int(1, 100) <= 60) {
                $images = glob(app_path(Post::TEST_IMAGES_GLOB_PATH), GLOB_BRACE);

                if ($images) {
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
