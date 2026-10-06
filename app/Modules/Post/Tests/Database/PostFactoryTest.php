<?php

declare(strict_types=1);

use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

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
        ->and(User::query()->count())->toBe(1);
});

it('does not fail when no seeding images are available', function (): void {
    expect(glob(app_path(Post::TEST_IMAGES_GLOB_PATH), GLOB_BRACE))->toBe([]);

    /** @var Factory<Post> $factory */
    $factory = Post::factory();

    $post = $factory->createOne();

    expect($post->getMedia(Post::POSTS_IMAGES_MEDIA_COLLECTION))->toHaveCount(0);
});
