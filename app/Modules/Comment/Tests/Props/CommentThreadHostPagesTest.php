<?php

declare(strict_types=1);

use App\Modules\Comment\Models\Comment;
use App\Modules\Comment\Props\CommentThreadProps;
use App\Modules\Post\Models\Post;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Build the URL of one of the pages that host the comments dialog.
 *
 * @param  array<string, mixed>  $query
 */
function commentHostUrl(string $routeName, array $query = []): string
{
    return $routeName === 'profile.show'
        ? route($routeName, ['user' => createUser(), ...$query])
        : route($routeName, $query);
}

/**
 * Headers of an Inertia partial reload that asks the host page only for the given prop.
 *
 * @return array<string, string>
 */
function commentHostPartialHeaders(TestCase $testCase, string $component, string $prop): array
{
    /** @var array{version: string} $page */
    $page = $testCase->get(route('home'))->inertiaPage();

    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $page['version'],
        'X-Inertia-Partial-Component' => $component,
        'X-Inertia-Partial-Data' => $prop,
    ];
}

dataset('comment host pages', [
    'home' => ['home', 'home/Home'],
    'own profile' => ['profile.index', 'profile/Profile'],
    "someone else's profile" => ['profile.show', 'profile/Profile'],
]);

it('keeps comments and replies out of a normal visit and leaves posts and user untouched', function (string $routeName, string $component): void {
    $user = createUser();
    $post = createPost($user);

    createComment(null, ['commentable_id' => $post->id, 'commentable_type' => Post::MORPH_NAME]);

    $this->actingAs($user)
        ->get(commentHostUrl($routeName, [CommentThreadProps::COMMENTS_PARAMETER => $post->id]))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component($component)
            ->has('user')
            ->has('posts')
            ->missing('comments')
            ->missing('replies')
        );
})->with('comment host pages');

it('delivers the comments of a post on a partial reload', function (string $routeName, string $component): void {
    $user = createUser();
    $post = createPost($user);

    foreach (range(1, 12) as $position) {
        createComment(null, ['commentable_id' => $post->id, 'commentable_type' => Post::MORPH_NAME]);
    }

    $this->actingAs($user)
        ->get(commentHostUrl($routeName, [CommentThreadProps::COMMENTS_PARAMETER => $post->id]))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component($component)
            ->reloadOnly('comments', fn (Assert $assert): Assert => $assert
                ->has('comments.data', 10)
                ->has('comments.data.0', fn (Assert $assert): Assert => $assert
                    ->hasAll(['id', 'content', 'createdAt', 'user', 'repliesInfo'])
                    ->etc()
                )
                ->whereType('comments.next_cursor', 'string')
            )
        );
})->with('comment host pages');

it('delivers the replies of a comment on a partial reload', function (string $routeName, string $component): void {
    $user = createUser();
    $post = createPost($user);
    $comment = createComment(null, ['commentable_id' => $post->id, 'commentable_type' => Post::MORPH_NAME]);

    foreach (range(1, 3) as $position) {
        createComment(null, ['commentable_id' => $comment->id, 'commentable_type' => Comment::MORPH_NAME]);
    }

    $this->actingAs($user)
        ->get(commentHostUrl($routeName, [CommentThreadProps::REPLIES_PARAMETER => $comment->id]))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component($component)
            ->reloadOnly('replies', fn (Assert $assert): Assert => $assert
                ->has('replies.data', 3)
                ->where('replies.next_cursor', null)
            )
        );
})->with('comment host pages');

it('answers 404 to a partial reload of a thread that does not exist', function (string $routeName, string $component): void {
    $this->actingAs(createUser());

    $this->get(
        commentHostUrl($routeName, [CommentThreadProps::COMMENTS_PARAMETER => 999999]),
        commentHostPartialHeaders($this, $component, 'comments'),
    )
        ->assertNotFound();
})->with('comment host pages');

it('answers 404 to a partial reload with a thread id that is not a positive integer', function (string $routeName, string $component): void {
    $this->actingAs(createUser());

    $this->get(
        commentHostUrl($routeName, [CommentThreadProps::REPLIES_PARAMETER => 'abc']),
        commentHostPartialHeaders($this, $component, 'replies'),
    )
        ->assertNotFound();
})->with('comment host pages');

it('redirects guests to the login page', function (string $routeName): void {
    $this->get(commentHostUrl($routeName, [CommentThreadProps::COMMENTS_PARAMETER => 1]))
        ->assertRedirect(route('login'));
})->with([
    'home' => ['home'],
    'own profile' => ['profile.index'],
    "someone else's profile" => ['profile.show'],
]);
