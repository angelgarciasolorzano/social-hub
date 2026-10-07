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

it('never attaches more than one seeding image to a post', function (): void {
    foreach (range(1, 10) as $attempt) {
        expect(createPost()->getMedia(Post::POSTS_IMAGES_MEDIA_COLLECTION)->count())->toBeLessThanOrEqual(1);
    }
});
