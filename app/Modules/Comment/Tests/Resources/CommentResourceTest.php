<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Resources\CommentResource;
use App\Modules\Post\Models\Post;

it('serializes only the public comment fields', function (): void {
    $user = createUser();
    $comment = createComment($user);

    $payload = new CommentResource($comment)->toArray(request());

    expect(array_keys($payload))->toBe(['id', 'content', 'createdAt', 'user', 'repliesInfo'])
        ->and($payload['id'])->toBe($comment->id)
        ->and($payload['content'])->toBe($comment->content)
        ->and($payload['createdAt'])->toBe($comment->created_at?->toIso8601String());
});

it('exposes only the id and name of the author', function (): void {
    $user = createUser();
    $comment = createComment($user);

    $payload = new CommentResource($comment)->toArray(request());

    expect($payload['user'])->toBe(['id' => $user->id, 'name' => $user->name]);
});

it('reports the replies info of the comment', function (): void {
    $comment = createComment();

    expect(new CommentResource($comment)->toArray(request())['repliesInfo'])
        ->toBe(['hasReplies' => false, 'repliesCount' => 0]);

    createComment(null, ['commentable_id' => $comment->id, 'commentable_type' => Comment::MORPH_NAME]);
    createComment(null, ['commentable_id' => $comment->id, 'commentable_type' => Comment::MORPH_NAME]);

    expect(new CommentResource($comment)->toArray(request())['repliesInfo'])
        ->toBe(['hasReplies' => true, 'repliesCount' => 2]);
});

it('keeps the cursor metadata when a page of comments is serialized through the resource', function (): void {
    $post = createPost();

    foreach (range(1, 11) as $index) {
        createComment(null, ['commentable_id' => $post->id, 'commentable_type' => Post::MORPH_NAME]);
    }

    $cursorPaginator = $post->comments()->with('user')->orderByDesc('id')->cursorPaginate(10)
        ->through(fn (Comment $comment): array => new CommentResource($comment)->resolve(request()));

    $payload = $cursorPaginator->toArray();

    expect($payload['data'])->toHaveCount(10)
        ->and($payload['per_page'])->toBe(10)
        ->and($cursorPaginator->hasMorePages())->toBeTrue()
        ->and($payload['next_cursor'])->toBeString()
        ->and($payload['prev_cursor'])->toBeNull();
});

it('reports no further pages when everything fits in one', function (): void {
    $post = createPost();
    createComment(null, ['commentable_id' => $post->id, 'commentable_type' => Post::MORPH_NAME]);

    $cursorPaginator = $post->comments()->with('user')->orderByDesc('id')->cursorPaginate(10)
        ->through(fn (Comment $comment): array => new CommentResource($comment)->resolve(request()));

    $payload = $cursorPaginator->toArray();

    expect($payload['data'])->toHaveCount(1)
        ->and($cursorPaginator->hasMorePages())->toBeFalse()
        ->and($payload['next_cursor'])->toBeNull();
});
