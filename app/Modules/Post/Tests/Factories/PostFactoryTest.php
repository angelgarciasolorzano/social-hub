<?php

declare(strict_types=1);

use App\Modules\Post\Database\Factories\PostFactory;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

it('creates a post with content and an existing owner', function (): void {
    /** @var Factory<Post> $factory */
    $factory = Post::factory();

    $post = $factory->createOne();

    expect($post->content)->not->toBeEmpty()
        ->and(User::query()->whereKey($post->user_id)->exists())->toBeTrue();
});

it('assigns the post to the given user', function (): void {
    $user = createUser();

    /** @var Factory<Post> $factory */
    $factory = Post::factory();

    $post = $factory->for($user)->createOne();

    expect($post->user_id)->toBe($user->id)
        ->and(User::query()->count('*'))->toBe(1);
});

it('creates posts without an image by default', function (): void {
    foreach (range(1, 10) as $attempt) {
        expect(createPost()->getMedia(Post::POSTS_IMAGES_MEDIA_COLLECTION))->toBeEmpty();
    }
});

it('attaches exactly one seeding image with the withImage state', function (): void {
    Storage::fake('public');

    withTemporarySeedingImages([Post::TEST_IMAGES_GLOB_PATH], function (): void {
        $post = PostFactory::new()->withImage()->createOne();

        expect($post->getMedia(Post::POSTS_IMAGES_MEDIA_COLLECTION))->toHaveCount(1);
    });
});

it('attaches no image when the withImage chance is zero', function (): void {
    Storage::fake('public');

    withTemporarySeedingImages([Post::TEST_IMAGES_GLOB_PATH], function (): void {
        $post = PostFactory::new()->withImage(0)->createOne();

        expect($post->getMedia(Post::POSTS_IMAGES_MEDIA_COLLECTION))->toBeEmpty();
    });
});
