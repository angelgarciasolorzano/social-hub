<?php

declare(strict_types=1);

namespace App\Modules\Comment\Props;

use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Resources\CommentResource;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\ProvidesInertiaProperties;
use Inertia\RenderContext;

final readonly class CommentThreadProps implements ProvidesInertiaProperties
{
    public const string COMMENTS_PARAMETER = 'comments_post';

    public const string COMMENTS_CURSOR = 'comments_cursor';

    public const string REPLIES_PARAMETER = 'replies_comment';

    public const string REPLIES_CURSOR = 'replies_cursor';

    private const string THROTTLE_PREFIX = 'comments.index';

    private const int THROTTLE_MAX_ATTEMPTS = 60;

    private const int THROTTLE_DECAY_SECONDS = 60;

    private const int PER_PAGE = 10;

    public function __construct(private Request $request) {}

    /**
     * Expose the comments of a post and the replies of a comment, loaded only on request.
     *
     * @return array<string, mixed>
     */
    public function toInertiaProperties(RenderContext $context): array
    {
        return [
            'comments' => Inertia::optional(fn (): CursorPaginator => $this->thread(
                Post::class,
                self::COMMENTS_PARAMETER,
                self::COMMENTS_CURSOR,
            )),
            'replies' => Inertia::optional(fn (): CursorPaginator => $this->thread(
                Comment::class,
                self::REPLIES_PARAMETER,
                self::REPLIES_CURSOR,
            )),
        ];
    }

    /**
     * Build the cursor-paginated comments of the commentable named by the request: the newest comments of a post first, the replies of a comment oldest first.
     *
     * @param  class-string<Post|Comment>  $commentableClass
     * @return CursorPaginator<int, array<array-key, mixed>>
     */
    private function thread(string $commentableClass, string $parameter, string $cursorName): CursorPaginator
    {
        $user = $this->consumeThrottleBudget();

        $commentable = $commentableClass === Comment::class
            ? Comment::withTrashed()->findOrFail($this->commentableId($parameter))
            : Post::query()->findOrFail($this->commentableId($parameter));

        $encodedCursor = $this->request->query($cursorName);

        $comments = $commentable->comments()
            ->withTrashed()
            ->with(['user', 'repliedToUser:id,name'])
            ->withCount(['comments', 'likes'])
            ->withExists(['likes as liked_by_me' => fn (Builder $builder): Builder => $builder->whereBelongsTo($user)])
            ->orderBy('id', $commentable instanceof Comment ? 'asc' : 'desc');

        return $comments
            ->cursorPaginate(
                perPage: self::PER_PAGE,
                cursorName: $cursorName,
                cursor: \is_string($encodedCursor) ? Cursor::fromEncoded($encodedCursor) : null,
            )
            ->through(fn (Comment $comment): array => new CommentResource($comment)->resolve($this->request));
    }

    /**
     * Read the commentable id from the query string, answering 404 when it is not a positive integer.
     */
    private function commentableId(string $parameter): int
    {
        $commentableId = filter_var($this->request->query($parameter), FILTER_VALIDATE_INT);

        abort_if($commentableId === false || $commentableId < 1, 404);

        return $commentableId;
    }

    /**
     * Count this load against the per-user budget, answering 429 once it is spent, and return the user.
     */
    private function consumeThrottleBudget(): User
    {
        $user = $this->request->user();

        abort_unless($user instanceof User, 401);

        $throttleKey = self::THROTTLE_PREFIX.'|'.$user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, self::THROTTLE_MAX_ATTEMPTS)) {
            throw new ThrottleRequestsException(
                'Too Many Attempts.',
                null,
                ['Retry-After' => (string) RateLimiter::availableIn($throttleKey)],
            );
        }

        RateLimiter::hit($throttleKey, self::THROTTLE_DECAY_SECONDS);

        return $user;
    }
}
