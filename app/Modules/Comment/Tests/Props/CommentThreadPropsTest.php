<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Props\CommentThreadProps;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Inertia\OptionalProp;
use Inertia\RenderContext;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Build the props the host pages receive for a request carrying the given query string.
 *
 * @param  array<string, mixed>  $query
 * @return array<string, mixed>
 */
function commentThreadProps(array $query, ?User $user): array
{
    $request = Request::create('/home', 'GET', $query);
    $request->setUserResolver(static fn (): ?User => $user);

    return new CommentThreadProps($request)->toInertiaProperties(new RenderContext('home/Home', $request));
}

/**
 * Resolve one of the optional props the way a partial reload does and return its paginator.
 *
 * @param  array<string, mixed>  $query
 * @return CursorPaginator<int, array<string, mixed>>
 */
function resolveCommentThread(string $prop, array $query, ?User $user): CursorPaginator
{
    /** @var OptionalProp $optionalProp */
    $optionalProp = commentThreadProps($query, $user)[$prop];

    $paginator = $optionalProp();

    throw_unless($paginator instanceof CursorPaginator, LogicException::class, 'The optional prop did not resolve to a cursor paginator.');

    /** @var CursorPaginator<int, array<string, mixed>> $paginator */
    return $paginator;
}

it('exposes comments and replies as optional props that run no query until they are resolved', function (): void {
    $user = createUser();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $props = commentThreadProps([CommentThreadProps::COMMENTS_PARAMETER => 1], $user);

    expect(array_keys($props))->toBe(['comments', 'replies'])
        ->and($props['comments'])->toBeInstanceOf(OptionalProp::class)
        ->and($props['replies'])->toBeInstanceOf(OptionalProp::class)
        ->and(DB::connection()->getQueryLog())->toBeEmpty();
});

it('lists the comments of a post ten at a time in descending id order with their own cursor', function (): void {
    $post = createPost();
    $otherPost = createPost();

    createCommentsOn($post, 12);
    $otherPostCommentIds = array_map(static fn (Comment $comment): int => $comment->id, createCommentsOn($otherPost, 3));

    $firstPage = resolveCommentThread('comments', [CommentThreadProps::COMMENTS_PARAMETER => $post->id], createUser());
    $ids = array_column($firstPage->items(), 'id');
    $descendingIds = $ids;
    rsort($descendingIds);

    expect($ids)->toHaveCount(10)
        ->and($ids)->not->toContain(...$otherPostCommentIds)
        ->and($ids)->toBe($descendingIds)
        ->and($firstPage->getCursorName())->toBe(CommentThreadProps::COMMENTS_CURSOR)
        ->and($firstPage->hasMorePages())->toBeTrue()
        ->and($firstPage->nextCursor()?->encode())->toBeString();
});

it('serves the next page from the comments cursor and ends without a further cursor', function (): void {
    $post = createPost();

    createCommentsOn($post, 12);
    $user = createUser();

    $firstPage = resolveCommentThread('comments', [CommentThreadProps::COMMENTS_PARAMETER => $post->id], $user);

    $secondPage = resolveCommentThread('comments', [
        CommentThreadProps::COMMENTS_PARAMETER => $post->id,
        CommentThreadProps::COMMENTS_CURSOR => $firstPage->nextCursor()?->encode(),
    ], $user);

    $firstPageIds = array_column($firstPage->items(), 'id');
    $secondPageIds = array_column($secondPage->items(), 'id');

    expect($secondPageIds)->toHaveCount(2)
        ->and($firstPageIds)->not->toContain(...$secondPageIds)
        ->and([...$firstPageIds, ...$secondPageIds])->toBe(collect([...$firstPageIds, ...$secondPageIds])->sortDesc()->values()->all())
        ->and($secondPage->hasMorePages())->toBeFalse()
        ->and($secondPage->nextCursor())->toBeNull();
});

it('lists the replies of a comment with the replies cursor and never mixes in other threads', function (): void {
    $post = createPost();

    [$comment, $otherComment] = createCommentsOn($post, 2);

    foreach (range(1, 11) as $position) {
        createCommentOn($comment);
    }

    createCommentOn($otherComment);

    $replies = resolveCommentThread('replies', [CommentThreadProps::REPLIES_PARAMETER => $comment->id], createUser());

    expect($replies->items())->toHaveCount(10)
        ->and($replies->getCursorName())->toBe(CommentThreadProps::REPLIES_CURSOR)
        ->and($replies->hasMorePages())->toBeTrue();
});

it('serializes each comment with the resource shape and nothing else', function (): void {
    $post = createPost();

    [$comment] = createCommentsOn($post, 1);
    createCommentOn($comment);

    $firstPage = resolveCommentThread('comments', [CommentThreadProps::COMMENTS_PARAMETER => $post->id], createUser());
    $serializedComment = $firstPage->items()[0];

    expect(array_keys($serializedComment))->toBe(['id', 'content', 'createdAt', 'user', 'repliedTo', 'repliesInfo', 'likesInfo'])
        ->and($serializedComment['user'])->toHaveKeys(['id', 'name'])->toHaveCount(2)
        ->and($serializedComment['repliedTo'])->toBeNull()
        ->and($serializedComment['repliesInfo'])->toBe(['hasReplies' => true, 'repliesCount' => 1])
        ->and($serializedComment['likesInfo'])->toBe(['count' => 0, 'likedByMe' => false]);
});

it('reports the likes of each comment and whether the viewer liked it', function (): void {
    $post = createPost();
    $user = createUser();
    $comment = createCommentOn($post);
    $likedByOthers = createCommentOn($post);
    $unliked = createCommentOn($post);

    createLike($user, $comment);
    createLike(createUser(), $comment);
    createLike(createUser(), $likedByOthers);

    $page = resolveCommentThread('comments', [CommentThreadProps::COMMENTS_PARAMETER => $post->id], $user);
    $likesInfo = array_column($page->items(), 'likesInfo', 'id');

    expect($likesInfo[$comment->id])->toBe(['count' => 2, 'likedByMe' => true])
        ->and($likesInfo[$likedByOthers->id])->toBe(['count' => 1, 'likedByMe' => false])
        ->and($likesInfo[$unliked->id])->toBe(['count' => 0, 'likedByMe' => false]);
});

it('reports the likes of each reply and whether the viewer liked it', function (): void {
    $comment = createComment();
    $user = createUser();
    $likedReply = createCommentOn($comment);
    $plainReply = createCommentOn($comment);

    createLike($user, $likedReply);

    $page = resolveCommentThread('replies', [CommentThreadProps::REPLIES_PARAMETER => $comment->id], $user);
    $likesInfo = array_column($page->items(), 'likesInfo', 'id');

    expect($likesInfo[$likedReply->id])->toBe(['count' => 1, 'likedByMe' => true])
        ->and($likesInfo[$plainReply->id])->toBe(['count' => 0, 'likedByMe' => false]);
});

it('serializes the mentioned user of each reply with only its id and name', function (): void {
    $comment = createComment();
    $mentionedUser = createUser();
    $mentioningReply = createCommentOn($comment, attributes: ['replied_to_user_id' => $mentionedUser->id]);
    $plainReply = createCommentOn($comment);

    $replies = resolveCommentThread('replies', [CommentThreadProps::REPLIES_PARAMETER => $comment->id], createUser());
    $serializedReplies = array_column($replies->items(), null, 'id');

    expect($serializedReplies[$mentioningReply->id]['repliedTo'])->toBe(['id' => $mentionedUser->id, 'name' => $mentionedUser->name])
        ->and($serializedReplies[$plainReply->id]['repliedTo'])->toBeNull();
});

it('lists a reply with a null mention when the mentioned user was deleted', function (): void {
    $comment = createComment();
    $mentionedUser = createUser();
    $reply = createCommentOn($comment, attributes: ['replied_to_user_id' => $mentionedUser->id]);

    User::query()->whereKey($mentionedUser->id)->delete();

    $replies = resolveCommentThread('replies', [CommentThreadProps::REPLIES_PARAMETER => $comment->id], createUser());

    expect($replies->items())->toHaveCount(1)
        ->and($replies->items()[0]['id'])->toBe($reply->id)
        ->and($replies->items()[0]['repliedTo'])->toBeNull();
});

it('serializes each page as a plain cursor paginator envelope with its own cursor name', function (): void {
    $post = createPost();
    createCommentsOn($post, 11);

    $paginator = resolveCommentThread('comments', [CommentThreadProps::COMMENTS_PARAMETER => $post->id], createUser());

    expect(array_keys($paginator->toArray()))->toBe([
        'data', 'path', 'per_page', 'next_cursor', 'next_page_url', 'prev_cursor', 'prev_page_url',
    ])
        ->and($paginator->getCursorName())->toBe(CommentThreadProps::COMMENTS_CURSOR)
        ->and($paginator->nextCursor()?->encode())->toBeString()
        ->and($paginator->previousCursor())->toBeNull();
});

it('answers 404 when the thread id is missing, not a positive integer or unknown', function (mixed $threadId): void {
    $query = $threadId === null ? [] : [CommentThreadProps::COMMENTS_PARAMETER => $threadId];

    expect(fn (): CursorPaginator => resolveCommentThread('comments', $query, createUser()))
        ->toThrow(HttpException::class);
})->with([
    'missing' => [null],
    'text' => ['abc'],
    'decimal' => ['1.5'],
    'zero' => ['0'],
    'negative' => ['-3'],
]);

it('fails with a model not found error for a thread that does not exist', function (): void {
    expect(fn (): CursorPaginator => resolveCommentThread('comments', [CommentThreadProps::COMMENTS_PARAMETER => 999999], createUser()))
        ->toThrow(ModelNotFoundException::class)
        ->and(fn (): CursorPaginator => resolveCommentThread('replies', [CommentThreadProps::REPLIES_PARAMETER => 999999], createUser()))
        ->toThrow(ModelNotFoundException::class);
});

it('refuses to resolve the props for a guest', function (): void {
    $post = createPost();

    expect(fn (): CursorPaginator => resolveCommentThread('comments', [CommentThreadProps::COMMENTS_PARAMETER => $post->id], null))
        ->toThrow(HttpException::class);
});

it('allows 60 loads per minute and then answers 429', function (): void {
    $post = createPost();
    $user = createUser();
    $query = [CommentThreadProps::COMMENTS_PARAMETER => $post->id];

    foreach (range(1, 60) as $attempt) {
        resolveCommentThread('comments', $query, $user);
    }

    expect(fn (): CursorPaginator => resolveCommentThread('comments', $query, $user))->toThrow(ThrottleRequestsException::class);
});

it('shares the read budget between comments and replies but keeps it per user', function (): void {
    $post = createPost();

    [$comment] = createCommentsOn($post, 1);

    $user = createUser();
    $bystander = createUser();

    foreach (range(1, 30) as $attempt) {
        resolveCommentThread('comments', [CommentThreadProps::COMMENTS_PARAMETER => $post->id], $user);
        resolveCommentThread('replies', [CommentThreadProps::REPLIES_PARAMETER => $comment->id], $user);
    }

    expect(fn (): CursorPaginator => resolveCommentThread('replies', [CommentThreadProps::REPLIES_PARAMETER => $comment->id], $user))
        ->toThrow(ThrottleRequestsException::class)
        ->and(resolveCommentThread('comments', [CommentThreadProps::COMMENTS_PARAMETER => $post->id], $bystander)->items())
        ->toHaveCount(1);
});

it('does not let reading comments consume the comment publishing budget', function (): void {
    $post = createPost();
    $user = createUser();

    foreach (range(1, 60) as $attempt) {
        resolveCommentThread('comments', [CommentThreadProps::COMMENTS_PARAMETER => $post->id], $user);
    }

    $this->actingAs($user)
        ->post(route('comments.store'), [
            'content' => 'Un comentario',
            'commentable_type' => Post::MORPH_NAME,
            'commentable_id' => $post->id,
        ])
        ->assertRedirect();
});

it('uses the same number of queries whether the thread has one or many comments and replies', function (): void {
    $smallPost = createPost();
    createCommentsOn($smallPost, 1);

    $largePost = createPost();

    foreach (createCommentsOn($largePost, 10) as $comment) {
        createCommentOn($comment);
        createLike(createUser(), $comment);
    }

    $user = createUser();

    $countQueries = static function (int $postId) use ($user): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        resolveCommentThread('comments', [CommentThreadProps::COMMENTS_PARAMETER => $postId], $user);

        $queries = count(DB::connection()->getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($countQueries($largePost->id))->toBe($countQueries($smallPost->id));
});
