<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Resources\CommentCollection;
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

it('wraps the comments with cursor metadata', function (): void {
    $post = createPost();

    foreach (range(1, 11) as $index) {
        createComment(null, ['commentable_id' => $post->id, 'commentable_type' => Post::MORPH_NAME]);
    }

    $cursorPaginator = $post->comments()->with('user')->orderByDesc('id')->cursorPaginate(10);

    /** @var array{data: list<array<string, mixed>>, meta: array<string, mixed>, links: array<string, mixed>} $payload */
    $payload = json_decode((string) json_encode(new CommentCollection($cursorPaginator)), true);

    expect($payload['data'])->toHaveCount(10)
        ->and(array_keys($payload))->toBe(['data', 'meta', 'links'])
        ->and(array_keys($payload['meta']))->toBe(['per_page', 'next_cursor', 'prev_cursor', 'has_more'])
        ->and($payload['meta']['per_page'])->toBe(10)
        ->and($payload['meta']['has_more'])->toBeTrue()
        ->and($payload['meta']['next_cursor'])->toBeString()
        ->and($payload['meta']['prev_cursor'])->toBeNull();
});

it('reports no further pages when everything fits in one', function (): void {
    $post = createPost();
    createComment(null, ['commentable_id' => $post->id, 'commentable_type' => Post::MORPH_NAME]);

    $cursorPaginator = $post->comments()->with('user')->orderByDesc('id')->cursorPaginate(10);

    /** @var array{data: list<array<string, mixed>>, meta: array<string, mixed>} $payload */
    $payload = json_decode((string) json_encode(new CommentCollection($cursorPaginator)), true);

    expect($payload['data'])->toHaveCount(1)
        ->and($payload['meta']['has_more'])->toBeFalse()
        ->and($payload['meta']['next_cursor'])->toBeNull();
});
