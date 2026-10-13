<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Resources\CommentResource;
use App\Modules\Post\Models\Post;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;

/**
 * @return CursorPaginator<int, array<mixed>>
 */
function commentsCursorPage(Post $post): CursorPaginator
{
    return $post->comments()->with('user')->orderByDesc('id')->cursorPaginate(10)
        ->through(fn (Comment $comment): array => new CommentResource($comment)->resolve(request()));
}

it('serializes only the public comment fields', function (): void {
    $user = createUser();
    $comment = createComment($user);

    $payload = new CommentResource($comment)->resolve(request());

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

it('exposes only the id and name of the mentioned user', function (): void {
    $mentionedUser = createUser();
    $reply = createCommentOn(createComment(), attributes: ['replied_to_user_id' => $mentionedUser->id]);

    $payload = new CommentResource($reply->load('repliedToUser'))->resolve(request());

    expect($payload['repliedTo'])->toBe(['id' => $mentionedUser->id, 'name' => $mentionedUser->name])
        ->and(json_encode($payload))->not->toContain($mentionedUser->email);
});

it('reports a null mention when the comment does not mention anyone', function (): void {
    $payload = new CommentResource(createComment()->load('repliedToUser'))->resolve(request());

    expect($payload)->toHaveKey('repliedTo')
        ->and($payload['repliedTo'])->toBeNull();
});

it('omits the mention without running queries when the relation is not loaded', function (): void {
    $mentionedUser = createUser();
    $reply = createCommentOn(createComment(), attributes: ['replied_to_user_id' => $mentionedUser->id])->load('user')->loadCount('comments');

    DB::enableQueryLog();
    $payload = new CommentResource($reply)->resolve(request());
    $queries = DB::connection()->getQueryLog();
    DB::disableQueryLog();

    expect($payload)->not->toHaveKey('repliedTo')
        ->and($queries)->toBeEmpty();
});

it('reports the replies info of the comment', function (): void {
    $comment = createComment();

    expect(new CommentResource($comment)->toArray(request())['repliesInfo'])
        ->toBe(['hasReplies' => false, 'repliesCount' => 0]);

    createCommentsOn($comment, 2);

    expect(new CommentResource($comment)->toArray(request())['repliesInfo'])
        ->toBe(['hasReplies' => true, 'repliesCount' => 2]);
});

it('keeps the cursor metadata when a page of comments is serialized through the resource', function (): void {
    $post = createPost();

    createCommentsOn($post, 11);

    $cursorPaginator = commentsCursorPage($post);

    $payload = $cursorPaginator->toArray();

    expect($payload['data'])->toHaveCount(10)
        ->and($payload['per_page'])->toBe(10)
        ->and($cursorPaginator->hasMorePages())->toBeTrue()
        ->and($payload['next_cursor'])->toBeString()
        ->and($payload['prev_cursor'])->toBeNull();
});

it('reports no further pages when everything fits in one', function (): void {
    $post = createPost();
    createCommentOn($post);

    $cursorPaginator = commentsCursorPage($post);

    $payload = $cursorPaginator->toArray();

    expect($payload['data'])->toHaveCount(1)
        ->and($cursorPaginator->hasMorePages())->toBeFalse()
        ->and($payload['next_cursor'])->toBeNull();
});
