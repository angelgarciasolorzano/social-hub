<?php

declare(strict_types=1);

use App\Modules\Friendship\Enums\FriendshipListTab;
use App\User\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Count the queries of one friends page request as the given user. Call it once before measuring: the
 * first request of a session runs one more query than the next ones.
 */
function friendsPageQueryCount(TestCase $testCase, User $user, FriendshipListTab $friendshipListTab): int
{
    $testCase->actingAs($user);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $testCase->get(route('friendships.index', ['tab' => $friendshipListTab->value]))->assertOk();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queries;
}

/**
 * Give the user the given number of friends, received requests and sent requests.
 */
function createFriendsPageRows(User $user, int $total): void
{
    foreach (range(1, $total) as $position) {
        createFriendship($user, createUser());
        createFriendship(createUser(), $user);
        createFriendRequest(createUser(), $user);
        createFriendRequest($user, createUser());
    }
}

it('sends a guest to the login page', function (): void {
    $this->get(route('friendships.index'))->assertRedirect(route('login'));
});

it('never lists the friendships or requests of other people in :dataset', function (FriendshipListTab $friendshipListTab): void {
    $user = createUser();
    $stranger = createUser();
    createFriendship($stranger, createUser());
    createFriendRequest($stranger, createUser());
    createFriendRequest(createUser(), $stranger);

    $this->actingAs($user)
        ->get(route('friendships.index', ['tab' => $friendshipListTab->value]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $assertableInertia): AssertableInertia => $assertableInertia
            ->has('friendships.data', 0)
            ->where('tabCounts', ['friends' => 0, 'received' => 0, 'sent' => 0]));
})->with([
    'friends' => [FriendshipListTab::Friends],
    'received' => [FriendshipListTab::Received],
    'sent' => [FriendshipListTab::Sent],
]);

it('allows 60 visits per minute and then answers 429', function (): void {
    $user = createUser();

    foreach (range(1, 60) as $attempt) {
        $this->actingAs($user)->get(route('friendships.index'))->assertOk();
    }

    $this->actingAs($user)
        ->get(route('friendships.index'))
        ->assertTooManyRequests();
});

it('runs the same number of queries in :dataset with few or many rows', function (FriendshipListTab $friendshipListTab): void {
    $userWithFewRows = createUser();
    createFriendsPageRows($userWithFewRows, 2);
    friendsPageQueryCount($this, $userWithFewRows, $friendshipListTab);
    $fewRowsQueries = friendsPageQueryCount($this, $userWithFewRows, $friendshipListTab);

    $userWithManyRows = createUser();
    createFriendsPageRows($userWithManyRows, 10);
    friendsPageQueryCount($this, $userWithManyRows, $friendshipListTab);
    $manyRowsQueries = friendsPageQueryCount($this, $userWithManyRows, $friendshipListTab);

    expect($manyRowsQueries)->toBe($fewRowsQueries);
})->with([
    'friends' => [FriendshipListTab::Friends],
    'received' => [FriendshipListTab::Received],
    'sent' => [FriendshipListTab::Sent],
]);
