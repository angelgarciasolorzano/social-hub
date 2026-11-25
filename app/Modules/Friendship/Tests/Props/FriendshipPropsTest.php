<?php

declare(strict_types=1);

use App\Modules\Friendship\Models\Friendship;
use App\Modules\Friendship\Props\FriendshipProps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * The props, the deferred prop names and the once keys of an Inertia page.
 *
 * @return array{props: array<mixed>, deferred: list<mixed>, once: list<int|string>}
 */
function friendshipPropsPageParts(mixed $page): array
{
    throw_unless(is_array($page), UnexpectedValueException::class, 'The Inertia page must be an array.');

    $props = $page['props'] ?? [];
    $deferredProps = $page['deferredProps'] ?? [];
    $onceProps = $page['onceProps'] ?? [];

    throw_unless(is_array($props) && is_array($deferredProps) && is_array($onceProps), UnexpectedValueException::class, 'The Inertia page has an invalid shape.');

    $deferred = [];

    foreach ($deferredProps as $deferredProp) {
        throw_unless(is_array($deferredProp), UnexpectedValueException::class, 'Each deferred group must be a list.');

        array_push($deferred, ...array_values($deferredProp));
    }

    return ['props' => $props, 'deferred' => $deferred, 'once' => array_keys($onceProps)];
}

/**
 * The headers of an Inertia visit to the props test page from a client that already holds the given
 * once keys, optionally asking only for the given props (a partial reload).
 *
 * @param  list<string>  $loadedOnceKeys
 * @return array<string, string>
 */
function friendshipPropsVisitHeaders(array $loadedOnceKeys, ?string $only = null): array
{
    /** @var array{version: string} $page */
    $page = get('/__test/friendship-props')->viewData('page');

    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $page['version'],
        'X-Inertia-Except-Once-Props' => implode(',', $loadedOnceKeys),
    ];

    if ($only !== null) {
        $headers['X-Inertia-Partial-Component'] = 'home/Home';
        $headers['X-Inertia-Partial-Data'] = $only;
    }

    return $headers;
}

beforeEach(function (): void {
    Route::middleware('web')->get('/__test/friendship-props', fn (Request $request): Response => Inertia::render('home/Home', [
        new FriendshipProps($request),
    ]));
});

it('defers the received requests and shares the count and the two newest', function (): void {
    $user = createUser();
    $friendRequests = array_map(function (int $daysAgo) use ($user): Friendship {
        $friendship = createFriendRequest(createUser(), $user);
        $friendship->created_at = now()->subDays($daysAgo);
        $friendship->save();

        return $friendship;
    }, range(1, 7));

    $connection = DB::connection();
    $connection->enableQueryLog();
    $connection->flushQueryLog();

    $testResponse = actingAs($user)->get('/__test/friendship-props');
    $friendshipQueries = collect($connection->getQueryLog())
        ->filter(fn (array $query): bool => str_contains(strtolower($query['query']), 'friendships'));
    $connection->disableQueryLog();

    expect($friendshipQueries)->toBeEmpty();

    $testResponse->assertInertia(fn (Assert $assert): Assert => $assert
        ->missing('friendRequests')
        ->loadDeferredProps(fn (Assert $deferredAssert): Assert => $deferredAssert
            ->where('friendRequests.pendingCount', 7)
            ->has('friendRequests.recent', FriendshipProps::RECENT_REQUESTS_LIMIT)
            ->where('friendRequests.recent.0.friendshipId', $friendRequests[0]->id)
            ->where('friendRequests.recent.1.friendshipId', $friendRequests[1]->id)
            ->where('friendRequests.recent.0.mutualFriendsCount', 0)
            ->where('friendRequests.recent.0.friendshipState', 'request_received')
            ->where('friendRequests.recent.0.user.id', $friendRequests[0]->requester_id)
            ->missing('friendRequests.recent.0.user.email')
        )
    );
});

it("leaves out sent requests, friendships, other people's requests and suspended senders", function (): void {
    $user = createUser();
    $friendship = createFriendRequest(createUser(), $user);
    createFriendRequest(createSuspendedUser(), $user);
    createFriendRequest($user, createUser());
    createFriendship(createUser(), $user);
    createFriendRequest(createUser(), createUser());

    actingAs($user)
        ->get('/__test/friendship-props')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->loadDeferredProps(fn (Assert $deferredAssert): Assert => $deferredAssert
                ->where('friendRequests.pendingCount', 1)
                ->has('friendRequests.recent', 1)
                ->where('friendRequests.recent.0.friendshipId', $friendship->id)
            )
        );
});

it('counts the friends each sender shares with the viewer', function (): void {
    $user = createUser();
    $sender = createUser();
    createFriendRequest($sender, $user);

    foreach (range(1, 2) as $position) {
        $sharedFriend = createUser();
        createFriendship($user, $sharedFriend);
        createFriendship($sharedFriend, $sender);
    }

    createFriendship($sender, createUser());
    $pendingWithViewer = createUser();
    createFriendRequest($user, $pendingWithViewer);
    createFriendship($sender, $pendingWithViewer);

    actingAs($user)
        ->get('/__test/friendship-props')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->loadDeferredProps(fn (Assert $deferredAssert): Assert => $deferredAssert
                ->where('friendRequests.recent.0.user.id', $sender->id)
                ->where('friendRequests.recent.0.mutualFriendsCount', 2)
            )
        );
});

it('runs the same number of queries with one request and with five', function (): void {
    $user = createUser();
    createFriendRequest(createUser(), $user);

    $deferredQueryCount = function () use ($user): int {
        $connection = DB::connection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        actingAs($user)
            ->get('/__test/friendship-props')
            ->assertInertia(fn (Assert $assert): Assert => $assert->loadDeferredProps(fn (Assert $deferredAssert): Assert => $deferredAssert));

        $queryCount = count($connection->getQueryLog());
        $connection->disableQueryLog();

        return $queryCount;
    };

    $deferredQueryCount();
    $oneRequestQueryCount = $deferredQueryCount();

    foreach (range(1, 4) as $index) {
        createFriendRequest(createUser(), $user);
    }

    expect($deferredQueryCount())->toBe($oneRequestQueryCount);
});

it('gives a guest null', function (): void {
    get('/__test/friendship-props')
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->missing('friendRequests')
            ->loadDeferredProps(fn (Assert $deferredAssert): Assert => $deferredAssert
                ->where('friendRequests', null)
            )
        );
});

it('loads the requests once per viewer, so a later visit keeps the value the client already holds', function (): void {
    $user = createUser();
    createFriendRequest(createUser(), $user);
    actingAs($user);

    $firstVisit = friendshipPropsPageParts(get('/__test/friendship-props')->viewData('page'));

    expect($firstVisit['deferred'])->toContain('friendRequests')
        ->and($firstVisit['once'])->toBe(['friendRequests.'.$user->id]);

    $laterVisit = friendshipPropsPageParts(get('/__test/friendship-props', friendshipPropsVisitHeaders(['friendRequests.'.$user->id]))->assertOk()->json());

    expect($laterVisit['props'])->not->toHaveKey('friendRequests')
        ->and($laterVisit['deferred'])->not->toContain('friendRequests');
});

it('defers the requests again for another account signed in on the same tab', function (): void {
    $previousUser = createUser();
    $user = createUser();
    actingAs($user);

    $visit = friendshipPropsPageParts(get('/__test/friendship-props', friendshipPropsVisitHeaders(['friendRequests.'.$previousUser->id]))->assertOk()->json());

    expect($visit['deferred'])->toContain('friendRequests');
});

it('still answers an explicit reload of the requests the client already holds', function (): void {
    $user = createUser();
    createFriendRequest(createUser(), $user);
    actingAs($user);

    $reload = friendshipPropsPageParts(get('/__test/friendship-props', friendshipPropsVisitHeaders(['friendRequests.'.$user->id], 'friendRequests'))->assertOk()->json());

    expect(data_get($reload['props'], 'friendRequests.pendingCount'))->toBe(1);
});
