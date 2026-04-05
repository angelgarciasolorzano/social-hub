<?php

namespace App\Post\Factories;

use App\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

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
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @phpstan-return array<model-property<Post>, mixed>
     */
    public function definition(): array
    {
        return [
            'content' => fake()->paragraph(rand(1, 50)),
            'user_id' => User::factory(),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure()
    {
        return $this->afterCreating(function (Post $post) {
            if (rand(1, 100) <= 60) {
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
