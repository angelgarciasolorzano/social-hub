<?php

declare(strict_types=1);

use App\Modules\Friendship\Models\Friendship;
use App\User\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Count the queries of one search request as the given user.
 */
function friendshipSearchQueryCount(TestCase $testCase, User $user): int
{
    $testCase->actingAs($user);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $testCase->getJson(route('friendships.search', ['q' => 'Ana']))->assertOk();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queries;
}

/**
 * Create people named "Ana …", each with a different relation to the viewer and one friend in common.
 */
function createSearchablePeople(User $user, int $total): void
{
    $commonFriend = createUser();
    createFriendship($user, $commonFriend);

    foreach (range(1, $total) as $position) {
        $person = createUser();
        $person->forceFill(['name' => "Ana {$position}"])->save();

        createFriendship($person, $commonFriend);

        match ($position % 3) {
            0 => createFriendship($user, $person),
            1 => createFriendRequest($person, $user),
            default => null,
        };
    }
}

it('asks a guest to sign in before sending a request', function (): void {
    $this->postJson(route('friendships.store', createUser()))->assertUnauthorized();

    expect(Friendship::query()->exists())->toBeFalse();
});

it('asks a guest to sign in before using :dataset', function (string $method, string $routeName): void {
    $friendship = createFriendRequest();

    $this->json($method, route($routeName, $friendship))->assertUnauthorized();

    expect($friendship->refresh()->isPending())->toBeTrue();
})->with([
    'accept' => ['POST', 'friendships.accept'],
    'decline' => ['DELETE', 'friendships.decline'],
    'cancel' => ['DELETE', 'friendships.cancel'],
]);

it('asks a guest to sign in before searching', function (): void {
    $this->getJson(route('friendships.search', ['q' => 'Ana']))->assertUnauthorized();
});

it('forbids a stranger to :dataset a pending request, whatever the input', function (string $method, string $routeName): void {
    $friendship = createFriendRequest();

    $this->actingAs(createUser())
        ->json($method, route($routeName, $friendship), ['status' => 'accepted', 'requester_id' => 'not-a-number'])
        ->assertForbidden();

    expect($friendship->refresh()->isPending())->toBeTrue();
})->with([
    'accept' => ['POST', 'friendships.accept'],
    'decline' => ['DELETE', 'friendships.decline'],
    'cancel' => ['DELETE', 'friendships.cancel'],
]);

it('forbids a stranger to remove a friendship', function (): void {
    $friendship = createFriendship();

    $this->actingAs(createUser())
        ->deleteJson(route('friendships.destroy', $friendship))
        ->assertForbidden();

    expect(Friendship::query()->whereKey($friendship->id)->exists())->toBeTrue();
});

it('allows 20 requests per minute and then answers 429 with Retry-After', function (): void {
    $user = createUser();

    foreach (range(1, 20) as $attempt) {
        $this->actingAs($user)->postJson(route('friendships.store', createUser()))->assertOk();
    }

    $this->actingAs($user)
        ->postJson(route('friendships.store', createUser()))
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

it('keeps the budget of sending requests apart from the one of answering them', function (): void {
    $user = createUser();

    foreach (range(1, 21) as $attempt) {
        $this->actingAs($user)->postJson(route('friendships.store', createUser()));
    }

    $this->actingAs($user)
        ->postJson(route('friendships.accept', createFriendRequest(createUser(), $user)))
        ->assertOk();
});

it('allows 60 searches per minute and then answers 429', function (): void {
    $user = createUser();

    foreach (range(1, 60) as $attempt) {
        $this->actingAs($user)->getJson(route('friendships.search', ['q' => 'Ana']))->assertOk();
    }

    $this->actingAs($user)
        ->getJson(route('friendships.search', ['q' => 'Ana']))
        ->assertTooManyRequests();
});

it('runs the same number of queries for a search with few or many results', function (): void {
    $user = createUser();
    createSearchablePeople($user, 3);
    $fewResultsQueries = friendshipSearchQueryCount($this, $user);

    Friendship::query()->delete();
    User::query()->where('name', 'like', 'Ana %')->delete();

    $viewerWithManyResults = createUser();
    createSearchablePeople($viewerWithManyResults, 15);
    $manyResultsQueries = friendshipSearchQueryCount($this, $viewerWithManyResults);

    expect($manyResultsQueries)->toBe($fewResultsQueries);
});
