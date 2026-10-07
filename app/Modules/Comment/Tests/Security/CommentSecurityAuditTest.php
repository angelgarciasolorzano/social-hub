<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Resources\CommentCollection;
use App\Modules\Post\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Inertia\Support\SessionKey;
use Tests\TestCase;

/**
 * Names of the middleware gathered for a named route.
 *
 * @return list<string>
 */
function commentRouteMiddleware(string $routeName): array
{
    $route = Route::getRoutes()->getByName($routeName);

    return $route === null ? [] : array_values(array_filter($route->gatherMiddleware(), is_string(...)));
}

/**
 * Count the queries needed to list and serialize one page of comments with their replies info.
 */
function commentListingQueryCount(TestCase $testCase, int $commentsCount): int
{
    $post = createPost();
    $user = createUser();

    foreach (range(1, $commentsCount) as $index) {
        createComment(null, ['commentable_id' => $post->id, 'commentable_type' => Post::MORPH_NAME]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $testCase->actingAs($user)
        ->get(route('comments.index', ['commentType' => Post::MORPH_NAME, 'commentableId' => $post->id]));

    /** @var array<string, mixed> $flash */
    $flash = session()->get(SessionKey::FLASH_DATA, []);
    json_encode($flash['comments']);

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queries;
}

// Hypothesis 1: rate limiting -------------------------------------------------

it('[H1] throttles comments.store to prevent comment flooding', function (): void {
    $middleware = commentRouteMiddleware('comments.store');

    expect(array_filter($middleware, fn (string $name): bool => str_starts_with($name, 'throttle')))->not->toBeEmpty();
});

it('[H1] throttles comments.index', function (): void {
    $middleware = commentRouteMiddleware('comments.index');

    expect(array_filter($middleware, fn (string $name): bool => str_starts_with($name, 'throttle')))->not->toBeEmpty();
});

// Hypothesis 2: commentable_id existence --------------------------------------

it('[H2] rejects a non-existent commentable without creating anything or failing with a server error', function (): void {
    $testResponse = $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => 'Un comentario',
            'commentable_type' => Post::MORPH_NAME,
            'commentable_id' => 999999,
        ]);

    expect($testResponse->status())->toBeIn([302, 404, 422])
        ->and(Comment::query()->count())->toBe(0);
});

// Hypothesis 3: content handling ----------------------------------------------

it('[H3] rejects whitespace-only content', function (): void {
    $post = createPost();

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => "   \n\t  ",
            'commentable_type' => Post::MORPH_NAME,
            'commentable_id' => $post->id,
        ])
        ->assertSessionHasErrors('content');

    expect(Comment::query()->count())->toBe(0);
});

it('[H3] rejects content made only of control characters', function (): void {
    $post = createPost();

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => "\0\x01\x02",
            'commentable_type' => Post::MORPH_NAME,
            'commentable_id' => $post->id,
        ])
        ->assertSessionHasErrors('content');

    expect(Comment::query()->count())->toBe(0);
});

it('[H3] never renders comments as raw HTML in the frontend', function (): void {
    $offenders = collect(File::allFiles(base_path('resources/js/modules/comments')))
        ->filter(fn (SplFileInfo $file): bool => str_contains((string) file_get_contents($file->getPathname()), 'dangerouslySetInnerHTML'))
        ->map(fn (SplFileInfo $file): string => $file->getPathname())
        ->values()
        ->all();

    expect($offenders)
        ->toBeEmpty();
});

// Hypothesis 4: reply nesting depth -------------------------------------------

it('[H4] refuses to reply to a reply so threads have a single level of nesting', function (): void {
    $comment = createComment();
    $reply = createComment(null, ['commentable_id' => $comment->id, 'commentable_type' => Comment::MORPH_NAME]);

    $countBefore = Comment::query()->count();

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => 'Una respuesta a una respuesta',
            'commentable_type' => Comment::MORPH_NAME,
            'commentable_id' => $reply->id,
        ])
        ->assertSessionHasErrors(['commentable_id' => 'No se puede responder a una respuesta.']);

    expect(Comment::query()->count())->toBe($countBefore);
});

// Hypothesis 5: unreachable / inconsistent flash branch -----------------------

it('[H5] answers the store action through a single Inertia flash channel', function (): void {
    $source = (string) file_get_contents(app_path('Modules/Comment/Controllers/CommentController.php'));

    expect($source)->not->toContain('back()->with(');
});

// Hypothesis 6: per-comment queries -------------------------------------------

it('[H6] serializes a page of comments with a constant number of queries', function (): void {
    expect(commentListingQueryCount($this, 8))->toBe(commentListingQueryCount($this, 2));
});

// Hypothesis 7: user data exposure --------------------------------------------

it('[H7] does not expose private user data in the serialized comments', function (): void {
    $user = createUser();
    $post = createPost();
    createComment($user, ['commentable_id' => $post->id, 'commentable_type' => Post::MORPH_NAME]);

    $cursorPaginator = $post->comments()->with('user')->cursorPaginate(10);
    $payload = (string) json_encode(new CommentCollection($cursorPaginator));

    expect($payload)->toContain($user->name)
        ->and($payload)->not->toContain($user->email)
        ->and($payload)->not->toContain('password')
        ->and($payload)->not->toContain('remember_token');
});

// Hypothesis 8: authorization and forged input --------------------------------

it('[H8] exposes no route to update or delete comments', function (): void {
    $commentRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (Illuminate\Routing\Route $route): bool => str_starts_with($route->uri(), 'comments'))
        ->map(fn (Illuminate\Routing\Route $route): string => (string) $route->getName())
        ->sort()
        ->values()
        ->all();

    expect($commentRoutes)->toBe(['comments.index', 'comments.store']);
});

it('[H8] ignores a forged user_id and attributes the comment to the authenticated user', function (): void {
    $user = createUser();
    $victim = createUser();
    $post = createPost();

    $this->actingAs($user)
        ->post(route('comments.store'), [
            'content' => 'Un comentario',
            'commentable_type' => Post::MORPH_NAME,
            'commentable_id' => $post->id,
            'user_id' => $victim->id,
        ]);

    expect(Comment::query()->sole()->user_id)->toBe($user->id);
});

it('[H8] refuses to comment on resources other than posts and comments', function (): void {
    $user = createUser();

    $this->actingAs(createUser())
        ->post(route('comments.store'), [
            'content' => 'Un comentario',
            'commentable_type' => 'user',
            'commentable_id' => $user->id,
        ])
        ->assertSessionHasErrors('commentable_type');

    expect(Comment::query()->count())->toBe(0);
});
