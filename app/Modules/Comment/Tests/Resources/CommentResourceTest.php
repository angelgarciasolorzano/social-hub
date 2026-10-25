<?php

declare(strict_types=1);

use App\Modules\Comment\Database\Factories\CommentFactory;
use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Resources\CommentResource;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Serialize the comment as the given user (or as a guest) would receive it.
 *
 * @return array<mixed>
 */
function commentPayloadAs(Comment $comment, ?User $user = null): array
{
    $request = Request::create('/');
    $request->setUserResolver(static fn (): ?User => $user);

    return new CommentResource($comment)->resolve($request);
}

it('serializes only the public comment fields', function (): void {
    $user = createUser();
    $comment = createComment($user);

    $payload = new CommentResource($comment)->resolve(request());

    expect(array_keys($payload))->toBe(['id', 'content', 'createdAt', 'editedAt', 'isDeleted', 'isMine', 'user', 'repliesInfo', 'likesInfo'])
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
    $replyComment = createCommentOn(createComment(), attributes: ['replied_to_user_id' => $mentionedUser->id]);

    $payload = new CommentResource($replyComment->load('repliedToUser'))->resolve(request());

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
    $replyComment = createCommentOn(createComment(), attributes: ['replied_to_user_id' => $mentionedUser->id])->load('user')->loadCount(['comments', 'likes']);

    DB::enableQueryLog();
    $payload = new CommentResource($replyComment)->resolve(request());
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

it('reports the number of likes of the comment', function (): void {
    $comment = createComment();

    expect(commentPayloadAs($comment))->toMatchArray(['likesInfo' => ['count' => 0, 'likedByMe' => false]]);

    createLike(likeable: $comment);
    createLike(likeable: $comment);

    expect(commentPayloadAs($comment))->toMatchArray(['likesInfo' => ['count' => 2, 'likedByMe' => false]]);
});

it('reports that the user liked the comment', function (): void {
    $user = createUser();
    $comment = createComment();
    createLike($user, $comment);

    expect(commentPayloadAs($comment, $user))->toMatchArray(['likesInfo' => ['count' => 1, 'likedByMe' => true]]);
});

it('reports that the user did not like the comment when only someone else did', function (): void {
    $comment = createComment();
    createLike(createUser(), $comment);

    expect(commentPayloadAs($comment, createUser()))->toMatchArray(['likesInfo' => ['count' => 1, 'likedByMe' => false]]);
});

it('reports that a guest did not like the comment without running a query for it', function (): void {
    $comment = createComment();
    $comment->load('user')->loadCount(['comments', 'likes']);

    DB::enableQueryLog();
    $payload = commentPayloadAs($comment);
    $queries = DB::connection()->getQueryLog();
    DB::disableQueryLog();

    expect($payload)->toMatchArray(['likesInfo' => ['count' => 0, 'likedByMe' => false]])
        ->and($queries)->toBeEmpty();
});

it('reuses the preloaded likes without running queries', function (): void {
    $user = createUser();
    $comment = createComment();
    createLike($user, $comment);

    $preloadedComment = Comment::query()
        ->with('user')
        ->withCount(['comments', 'likes'])
        ->withExists(['likes as liked_by_me' => fn (Builder $builder): Builder => $builder->whereBelongsTo($user)])
        ->findOrFail($comment->id);

    DB::enableQueryLog();
    $payload = commentPayloadAs($preloadedComment, $user);
    $queries = DB::connection()->getQueryLog();
    DB::disableQueryLog();

    expect($payload)->toMatchArray(['likesInfo' => ['count' => 1, 'likedByMe' => true]])
        ->and($queries)->toBeEmpty();
});

it('reports null as the edited date of a comment that was never edited', function (): void {
    expect(commentPayloadAs(createComment()))->toMatchArray(['editedAt' => null, 'isDeleted' => false]);
});

it('reports the edited date of an edited comment', function (): void {
    $comment = CommentFactory::new()->edited()->createOne();

    expect(commentPayloadAs($comment->fresh() ?? $comment)['editedAt'])->toBe($comment->edited_at?->toIso8601String());
});

it('marks the comment as mine for its author', function (): void {
    $user = createUser();
    $comment = createComment($user);

    expect(commentPayloadAs($comment, $user))->toMatchArray(['isMine' => true]);
});

it('does not mark the comment as mine for another user', function (): void {
    $comment = createComment(createUser());

    expect(commentPayloadAs($comment, createUser()))->toMatchArray(['isMine' => false]);
});

it('does not mark the comment as mine for a guest', function (): void {
    $comment = createComment(createUser());

    expect(commentPayloadAs($comment))->toMatchArray(['isMine' => false]);
});

it('serializes a deleted comment as a tombstone without its text or author', function (): void {
    $user = createUser();
    $comment = CommentFactory::new()->for($user)->trashed()->createOne(['content' => 'Texto que debe desaparecer']);
    createCommentOn($comment);

    $payload = commentPayloadAs($comment, $user);

    expect($payload)->toMatchArray([
        'id' => $comment->id,
        'content' => null,
        'user' => null,
        'isDeleted' => true,
        'editedAt' => null,
        'repliesInfo' => ['hasReplies' => true, 'repliesCount' => 1],
        'likesInfo' => ['count' => 0, 'likedByMe' => false],
        'isMine' => false,
    ])
        ->and(json_encode($payload))->not->toContain('Texto que debe desaparecer')
        ->and(json_encode($payload))->not->toContain($user->name);
});

it('serializes a tombstone without running queries', function (): void {
    $comment = CommentFactory::new()->trashed()->createOne();
    createCommentOn($comment);
    $loadedComment = Comment::withTrashed()->withCount('comments')->findOrFail($comment->id);

    DB::enableQueryLog();
    commentPayloadAs($loadedComment);
    $queries = DB::connection()->getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
});

it('serializes the author flag without running queries', function (): void {
    $user = createUser();
    $comment = createComment($user);
    $loadedComment = Comment::query()
        ->with('user')
        ->withCount(['comments', 'likes'])
        ->withExists(['likes as liked_by_me' => fn (Builder $builder): Builder => $builder->whereBelongsTo($user)])
        ->findOrFail($comment->id);

    DB::enableQueryLog();
    $payload = commentPayloadAs($loadedComment, $user);
    $queries = DB::connection()->getQueryLog();
    DB::disableQueryLog();

    expect($payload)->toMatchArray(['isMine' => true])
        ->and($queries)->toBeEmpty();
});
