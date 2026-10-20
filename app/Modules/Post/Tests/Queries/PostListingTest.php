<?php

declare(strict_types=1);

use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Build the URL of one of the pages that list posts, with the owner of the profile when it is one.
 */
function postListingUrl(string $routeName, ?User $user = null): string
{
    return $routeName === 'profile.show'
        ? route($routeName, ['user' => $user])
        : route($routeName);
}

/**
 * Count the queries the page runs for the given user; the first visit of a process also loads one-time data.
 */
function countListingQueries(TestCase $testCase, string $url, User $user): int
{
    $testCase->actingAs($user);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $testCase->get($url)->assertOk();

    $queries = count(DB::connection()->getQueryLog());
    DB::disableQueryLog();

    return $queries;
}

/**
 * Give the post a reply thread and a like so its engagement is not empty.
 */
function addEngagementTo(Post $post, User $user): void
{
    createCommentOn(createCommentOn($post));
    createLike($user, $post);
}

dataset('post listing pages', [
    'home' => ['home', 'home/Home'],
    'own profile' => ['profile.index', 'profile/Profile'],
    "someone else's profile" => ['profile.show', 'profile/Profile'],
]);

it('serves the real comments and likes of each post', function (string $routeName, string $component): void {
    $user = createUser();
    $profileOwner = $routeName === 'profile.index' ? $user : createUser();
    $post = createPost($profileOwner);
    addEngagementTo($post, $user);

    $this->actingAs($user)
        ->get(postListingUrl($routeName, $profileOwner))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component($component)
            ->where('posts.data.0.id', $post->id)
            ->where('posts.data.0.commentsCount', 2)
            ->where('posts.data.0.likesInfo', ['count' => 1, 'likedByMe' => true]));
})->with('post listing pages');

it('serves each post with its own author, not the authenticated user', function (): void {
    $user = createUser();
    $author = createUser();
    createPost($author);

    $this->actingAs($user)
        ->get(route('home'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('posts.data.0.user.id', $author->id)
            ->where('posts.data.0.user.name', $author->name)
            ->missing('posts.data.0.user.email'));
});

it('counts zero comments and likes for a post without activity', function (string $routeName, string $component): void {
    $user = createUser();
    $profileOwner = $routeName === 'profile.index' ? $user : createUser();
    createPost($profileOwner);

    $this->actingAs($user)
        ->get(postListingUrl($routeName, $profileOwner))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->component($component)
            ->where('posts.data.0.commentsCount', 0)
            ->where('posts.data.0.likesInfo', ['count' => 0, 'likedByMe' => false]));
})->with('post listing pages');

it('counts only the replies of a deleted comment when the comment itself is deleted', function (): void {
    $user = createUser();
    $post = createPost($user);
    $deletedComment = createCommentOn($post);
    createCommentsOn($deletedComment, 3);
    $deletedComment->delete();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('posts.data.0.commentsCount', 3));
});

it('uses the same number of queries when the posts come from many different authors', function (): void {
    $user = createUser();

    createPost(createUser());
    countListingQueries($this, route('home'), $user);
    $queriesWithOneAuthor = countListingQueries($this, route('home'), $user);

    foreach (range(1, 9) as $attempt) {
        createPost(createUser());
    }

    expect(countListingQueries($this, route('home'), $user))->toBe($queriesWithOneAuthor);
});

it('uses the same number of queries however many posts the page lists', function (string $routeName): void {
    $user = createUser();
    $profileOwner = $routeName === 'profile.index' ? $user : createUser();

    addEngagementTo(createPost($profileOwner), $user);
    countListingQueries($this, postListingUrl($routeName, $profileOwner), $user);
    $queriesWithOnePost = countListingQueries($this, postListingUrl($routeName, $profileOwner), $user);

    foreach (range(1, 9) as $attempt) {
        addEngagementTo(createPost($profileOwner), $user);
    }

    expect(countListingQueries($this, postListingUrl($routeName, $profileOwner), $user))->toBe($queriesWithOnePost);
})->with(['home', 'profile.index', 'profile.show']);
