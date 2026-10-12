<?php

declare(strict_types=1);

use App\Modules\Comment\Props\CommentThreadProps;
use Inertia\Testing\AssertableInertia as Assert;

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

dataset('comment host pages', [
    'home' => ['home', 'home/Home'],
    'own profile' => ['profile.index', 'profile/Profile'],
    "someone else's profile" => ['profile.show', 'profile/Profile'],
]);

it('keeps comments and replies out of a normal visit and leaves posts and user untouched', function (string $routeName, string $component): void {
    $user = createUser();
    $post = createPost($user);

    createCommentOn($post);

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
        createCommentOn($post);
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
    $comment = createCommentOn($post);

    foreach (range(1, 3) as $position) {
        createCommentOn($comment);
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

it('lists the replies of a comment with their author', function (string $routeName, string $component): void {
    $user = createUser();
    $author = createUser();
    $comment = createComment();
    $reply = createCommentOn($comment, $author);

    $this->actingAs($user)
        ->get(commentHostUrl($routeName, [CommentThreadProps::REPLIES_PARAMETER => $comment->id]))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component($component)
            ->reloadOnly('replies', fn (Assert $assert): Assert => $assert
                ->has('replies.data', 1)
                ->where('replies.data.0.id', $reply->id)
                ->where('replies.data.0.user', ['id' => $author->id, 'name' => $author->name])
                ->where('replies.next_cursor', null)
            )
        );
})->with('comment host pages');

it('delivers an empty page for a post without comments', function (string $routeName, string $component): void {
    $user = createUser();
    $post = createPost($user);

    $this->actingAs($user)
        ->get(commentHostUrl($routeName, [CommentThreadProps::COMMENTS_PARAMETER => $post->id]))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component($component)
            ->reloadOnly('comments', fn (Assert $assert): Assert => $assert
                ->has('comments.data', 0)
                ->where('comments.next_cursor', null)
            )
        );
})->with('comment host pages');

it('answers 404 to a partial reload of a thread that does not exist', function (string $routeName, string $component): void {
    $this->actingAs(createUser());

    $this->get(
        commentHostUrl($routeName, [CommentThreadProps::COMMENTS_PARAMETER => 999999]),
        inertiaPartialHeaders($this, $component, 'comments'),
    )
        ->assertNotFound();
})->with('comment host pages');

it('answers 404 to a partial reload with a thread id that is not a positive integer', function (string $routeName, string $component): void {
    $this->actingAs(createUser());

    $this->get(
        commentHostUrl($routeName, [CommentThreadProps::REPLIES_PARAMETER => 'abc']),
        inertiaPartialHeaders($this, $component, 'replies'),
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
