<?php

declare(strict_types=1);

use App\User\Enums\PreferredLocale;
use App\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Visit the home page as the given user, with the given query string.
 *
 * @param  array<string, string>  $query
 * @return TestResponse<Response>
 */
function visitHomeFeedAs(TestCase $testCase, User $user, array $query = []): TestResponse
{
    return $testCase->actingAs($user)->get(route('home', $query))->assertOk();
}

/**
 * The ids of the posts the home page served, in the order it served them.
 *
 * @param  TestResponse<Response>  $testResponse
 * @return list<int>
 */
function homeFeedPostIds(TestResponse $testResponse): array
{
    /** @var array{props: array{posts: array{data: list<array{id: int}>}}} $page */
    $page = $testResponse->viewData('page');

    return array_column($page['props']['posts']['data'], 'id');
}

/**
 * Count the queries of one visit to the home page, after a first visit that loads the one-time data.
 */
function countHomeFeedQueries(TestCase $testCase, User $user): int
{
    visitHomeFeedAs($testCase, $user);

    DB::flushQueryLog();
    DB::enableQueryLog();

    visitHomeFeedAs($testCase, $user);

    $queries = count(DB::connection()->getQueryLog());
    DB::disableQueryLog();

    return $queries;
}

it('serves only the own posts and the posts of friends, newest first, also on the next page', function (): void {
    $user = createUser();
    $friend = createUser();
    createFriendship($friend, $user);
    $feedPostIds = [];

    foreach (range(1, 6) as $attempt) {
        $feedPostIds[] = createPost($user)->id;
        createPost();
        $feedPostIds[] = createPost($friend)->id;
    }

    $firstPage = visitHomeFeedAs($this, $user);

    /** @var array{scrollProps: array{posts: array{pageName: string, nextPage: string}}} $page */
    $page = $firstPage->viewData('page');
    $scrollProps = $page['scrollProps']['posts'];

    $secondPage = visitHomeFeedAs($this, $user, [$scrollProps['pageName'] => $scrollProps['nextPage']]);

    $newestFirst = array_reverse($feedPostIds);

    expect(homeFeedPostIds($firstPage))->toBe(array_slice($newestFirst, 0, 10))
        ->and(homeFeedPostIds($secondPage))->toBe(array_slice($newestFirst, 10));
});

it('uses the same number of queries with 2 and with 30 friends', function (): void {
    $user = createUser();

    foreach (range(1, 2) as $attempt) {
        $friend = createUser();
        createFriendship($user, $friend);
        createPost($friend);
    }

    $queriesWithTwoFriends = countHomeFeedQueries($this, $user);

    foreach (range(1, 28) as $attempt) {
        $friend = createUser();
        createFriendship($friend, $user);
        createPost($friend);
    }

    expect(countHomeFeedQueries($this, $user))->toBe($queriesWithTwoFriends);
});

it('shares the texts of the feed', function (): void {
    $user = createUser();

    visitHomeFeedAs($this, $user)
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('translations.home/feed.states.end', translatedTextIn(PreferredLocale::ENGLISH, 'home/feed.states.end')));
});
