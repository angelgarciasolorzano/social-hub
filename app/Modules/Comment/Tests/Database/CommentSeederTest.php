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

it('makes some replies mention the author of another reply in the same thread', function (): void {
    createUser();
    createUser();
    createUser();
    createPost();

    $this->seed(CommentSeeder::class);

    $mentioningReplies = Comment::query()->whereNotNull('replied_to_user_id')->get();

    expect($mentioningReplies)->not->toBeEmpty();

    foreach ($mentioningReplies as $mentioningReply) {
        $threadAuthorIds = Comment::query()
            ->where('commentable_type', Comment::MORPH_NAME)
            ->where('commentable_id', $mentioningReply->commentable_id)
            ->whereKeyNot($mentioningReply->id)
            ->pluck('user_id')
            ->all();

        expect($threadAuthorIds)->toContain($mentioningReply->replied_to_user_id)
            ->and($mentioningReply->replied_to_user_id)->not->toBe($mentioningReply->user_id);
    }
});

it('never mentions anyone on root comments or keeps threads to a single level', function (): void {
    createUser();
    createUser();
    createPost();

    $this->seed(CommentSeeder::class);

    $replyIds = Comment::query()->where('commentable_type', Comment::MORPH_NAME)->pluck('id')->all();

    expect(Comment::query()->where('commentable_type', Post::MORPH_NAME)->whereNotNull('replied_to_user_id')->count())->toBe(0)
        ->and(Comment::query()->where('commentable_type', Comment::MORPH_NAME)->whereIn('commentable_id', $replyIds)->count())->toBe(0);
});
