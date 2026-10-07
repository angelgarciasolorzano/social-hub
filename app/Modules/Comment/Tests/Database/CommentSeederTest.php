<?php

declare(strict_types=1);

use App\Modules\Comment\Database\Seeders\CommentSeeder;
use App\Modules\Comment\Models\Comment;
use App\Modules\Post\Models\Post;

it('seeds between 5 and 10 root comments for each post', function (): void {
    $posts = [createPost(), createPost()];

    $this->seed(CommentSeeder::class);

    foreach ($posts as $post) {
        expect($post->comments()->count())->toBeBetween(5, 10);
    }
});

it('seeds between 5 and 8 replies for each root comment', function (): void {
    $post = createPost();

    $this->seed(CommentSeeder::class);

    $rootComments = $post->comments()->get();

    expect($rootComments)->not->toBeEmpty();

    foreach ($rootComments as $rootComment) {
        expect($rootComment->repliesCount())->toBeBetween(5, 8);
    }
});

it('attributes every seeded comment to an existing user', function (): void {
    createPost();

    $this->seed(CommentSeeder::class);

    expect(Comment::query()->whereNull('user_id')->count())->toBe(0)
        ->and(Comment::query()->count())->toBeGreaterThan(0);
});

it('creates no comments when there are no posts', function (): void {
    createUser();

    $this->seed(CommentSeeder::class);

    expect(Post::query()->count())->toBe(0)
        ->and(Comment::query()->count())->toBe(0);
});
