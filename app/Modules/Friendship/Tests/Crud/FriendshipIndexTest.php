<?php

declare(strict_types=1);

use App\Modules\Friendship\Enums\FriendshipListSort;
use App\Modules\Friendship\Enums\FriendshipListTab;
use App\Modules\Friendship\Models\Friendship;
use App\Modules\Friendship\Requests\FriendshipIndexRequest;
use App\Modules\Friendship\Resources\FriendshipListItemResource;
use App\User\Enums\PreferredLocale;
use App\User\Enums\UserImageType;
use App\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Open the friends page as the given user, the way the browser does.
 *
 * @param  array<string, string>  $query
 * @return TestResponse<Response>
 */
function visitFriendsPageAs(TestCase $testCase, User $user, array $query = []): TestResponse
{
    return $testCase->actingAs($user)->get(route('friendships.index', $query));
}

/**
 * Create a user with the given name for the friends page tests.
 */
function friendsPageUserNamed(string $name): User
{
    $user = createUser();
    $user->forceFill(['name' => $name])->save();

    return $user;
}

/**
 * The rows the friends page received.
 *
 * @param  TestResponse<Response>  $testResponse
 * @return list<array{friendshipId: int, friendshipState: string, since: string|null, mutualFriendsCount: int, user: array{id: int, name: string, profilePicture: string, coverImage: string|null}}>
 */
function friendsPageRows(TestResponse $testResponse): array
{
    $page = $testResponse->viewData('page');

    expect($page)->toBeArray();

    /** @var array{props: array{friendships: array{data: list<array{friendshipId: int, friendshipState: string, since: string|null, mutualFriendsCount: int, user: array{id: int, name: string, profilePicture: string, coverImage: string|null}}>}}} $page */
    return $page['props']['friendships']['data'];
}

/**
 * The names of the other people the friends page lists, in order.
 *
 * @param  TestResponse<Response>  $testResponse
 * @return list<string>
 */
function friendsPageNames(TestResponse $testResponse): array
{
    return array_map(fn (array $row): string => $row['user']['name'], friendsPageRows($testResponse));
}

/**
 * Create a row of the given tab between the viewer and a new person with the given name.
 */
function createFriendsPageRow(User $user, FriendshipListTab $friendshipListTab, string $name): Friendship
{
    return match ($friendshipListTab) {
        FriendshipListTab::Friends => createFriendship(friendsPageUserNamed($name), $user),
        FriendshipListTab::Received => createFriendRequest(friendsPageUserNamed($name), $user),
        FriendshipListTab::Sent => createFriendRequest($user, friendsPageUserNamed($name)),
    };
}

/**
 * The number of queries the friends page runs for the given user and query string. Visit the page once
 * before measuring: the first request of a session runs one more query than the next ones.
 *
 * @param  array<string, string>  $query
 */
function friendsPageQueriesWith(TestCase $testCase, User $user, array $query): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    visitFriendsPageAs($testCase, $user, $query)->assertOk();

    $queryCount = count(DB::connection()->getQueryLog());
    DB::disableQueryLog();

    return $queryCount;
}

it('shows the friends tab by default with the filters and the counters', function (): void {
    $user = createUser();
    createFriendship($user, createUser());
    createFriendRequest(createUser(), $user);
    createFriendRequest($user, createUser());
    createFriendRequest($user, createUser());

    visitFriendsPageAs($this, $user)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $assertableInertia): AssertableInertia => $assertableInertia
            ->component('friendship/pages/Friends')
            ->where('filters', ['tab' => FriendshipListTab::Friends->value, 'q' => '', 'sort' => FriendshipListSort::Recent->value])
            ->where('tabCounts', ['friends' => 1, 'received' => 1, 'sent' => 2])
            ->has('friendships.data', 1));
});

it("serializes each row with only the other person's public data", function (): void {
    $user = createUser();
    $friend = friendsPageUserNamed('Ana Amiga');
    $friendship = createFriendship($friend, $user);

    expect(friendsPageRows(visitFriendsPageAs($this, $user)->assertOk()))->toBe([[
        'friendshipId' => $friendship->id,
        'friendshipState' => 'friends',
        'since' => $friendship->accepted_at?->toIso8601String(),
        'mutualFriendsCount' => 0,
        'user' => [
            'id' => $friend->id,
            'name' => 'Ana Amiga',
            'profilePicture' => $friend->getFirstMediaUrl(UserImageType::PROFILE_PICTURE->value()),
            'coverImage' => null,
        ],
    ]]);
});

it("serializes the other person's own cover image when they have one", function (): void {
    Storage::fake('public');
    $user = createUser();
    $friend = friendsPageUserNamed('Ana Amiga');
    $friend->addMedia(UploadedFile::fake()->image('cover.jpg'))
        ->toMediaCollection(UserImageType::COVER_IMAGE->value());
    createFriendship($friend, $user);

    $rows = friendsPageRows(visitFriendsPageAs($this, $user)->assertOk());

    expect($rows[0]['user']['coverImage'] ?? null)
        ->toBe($friend->refresh()->getFirstMediaUrl(UserImageType::COVER_IMAGE->value()))
        ->not->toBe(User::DEFAULT_COVER_IMAGE_PATH);
});

it('lists in :dataset only its rows, newest first, with the matching state', function (FriendshipListTab $friendshipListTab, string $friendshipState): void {
    $user = createUser();

    $createRow = fn (string $name): Friendship => match ($friendshipListTab) {
        FriendshipListTab::Friends => createFriendship(friendsPageUserNamed($name), $user),
        FriendshipListTab::Received => createFriendRequest(friendsPageUserNamed($name), $user),
        FriendshipListTab::Sent => createFriendRequest($user, friendsPageUserNamed($name)),
    };

    $now = now();

    Date::setTestNow($now->copy()->subDays(2));
    $olderFriendship = $createRow('Older');
    Date::setTestNow($now->copy()->subDay());
    $newerFriendship = $createRow('Newer');
    Date::setTestNow();

    createFriendship($user, friendsPageUserNamed('Friend elsewhere'));
    createFriendRequest(friendsPageUserNamed('Received elsewhere'), $user);
    createFriendRequest($user, friendsPageUserNamed('Sent elsewhere'));

    $rows = friendsPageRows(visitFriendsPageAs($this, $user, ['tab' => $friendshipListTab->value])->assertOk());
    $ownRows = array_values(array_filter($rows, fn (array $row): bool => ! str_ends_with($row['user']['name'], 'elsewhere')));

    expect(array_column($ownRows, 'friendshipId'))->toBe([$newerFriendship->id, $olderFriendship->id])
        ->and(array_unique(array_column($rows, 'friendshipState')))->toBe([$friendshipState]);
})->with([
    'friends' => [FriendshipListTab::Friends, 'friends'],
    'received' => [FriendshipListTab::Received, 'request_received'],
    'sent' => [FriendshipListTab::Sent, 'request_sent'],
]);

it("filters by the start of the other person's name without changing the counters", function (): void {
    $user = createUser();
    createFriendRequest(friendsPageUserNamed('Ana Inicio'), $user);
    createFriendRequest(friendsPageUserNamed('Mariana Medio'), $user);
    createFriendRequest(friendsPageUserNamed('Bruno'), $user);

    $testResponse = visitFriendsPageAs($this, $user, ['tab' => 'received', 'q' => 'ana'])->assertOk();

    expect(friendsPageNames($testResponse))->toBe(['Ana Inicio']);
    $testResponse->assertInertia(fn (AssertableInertia $assertableInertia): AssertableInertia => $assertableInertia
        ->where('tabCounts.received', 3)
        ->where('filters.q', 'ana'));
});

it('treats :dataset in the filter as a literal character', function (string $term, string $expectedName): void {
    $user = createUser();
    createFriendship($user, friendsPageUserNamed('A%na'));
    createFriendship($user, friendsPageUserNamed('A_na'));
    createFriendship($user, friendsPageUserNamed('Abc'));

    expect(friendsPageNames(visitFriendsPageAs($this, $user, ['q' => $term])->assertOk()))->toBe([$expectedName]);
})->with([
    'a percent sign' => ['A%', 'A%na'],
    'an underscore' => ['A_', 'A_na'],
]);

it('leaves suspended people out of the lists and of the counters', function (): void {
    $user = createUser();
    createFriendship($user, createSuspendedUser());
    createFriendRequest(createSuspendedUser(), $user);
    createFriendRequest($user, createSuspendedUser());

    $testResponse = visitFriendsPageAs($this, $user)->assertOk();

    expect(friendsPageRows($testResponse))->toBeEmpty();
    $testResponse->assertInertia(fn (AssertableInertia $assertableInertia): AssertableInertia => $assertableInertia
        ->where('tabCounts', ['friends' => 0, 'received' => 0, 'sent' => 0]));
});

it('loads the next page with the cursor the infinite scroll sends, keeping the tab', function (): void {
    config(['module.friendship.list_page_size' => 2]);
    $user = createUser();

    $now = now();

    foreach (['Uno', 'Dos', 'Tres'] as $position => $name) {
        Date::setTestNow($now->copy()->subDays(3 - $position));
        createFriendRequest(friendsPageUserNamed($name), $user);
    }

    Date::setTestNow();

    $testResponse = visitFriendsPageAs($this, $user, ['tab' => 'received'])->assertOk();

    /** @var array{scrollProps: array{friendships: array{pageName: string, nextPage: string}}} $page */
    $page = $testResponse->viewData('page');
    $scrollProps = $page['scrollProps']['friendships'];

    $secondPage = visitFriendsPageAs($this, $user, ['tab' => 'received', $scrollProps['pageName'] => $scrollProps['nextPage']])->assertOk();

    expect(friendsPageNames($testResponse))->toBe(['Tres', 'Dos'])
        ->and(friendsPageNames($secondPage))->toBe(['Uno']);
});

it('rejects :dataset', function (string $field, string $value, string $messageKey): void {
    $expectedMessage = str_replace(
        ':max',
        (string) FriendshipIndexRequest::TERM_MAX_LENGTH,
        translatedTextIn(PreferredLocale::ENGLISH, $messageKey),
    );

    visitFriendsPageAs($this, createUser(), [$field => $value])->assertSessionHasErrors([$field => $expectedMessage]);
})->with([
    'an unknown tab' => ['tab', 'unknown', 'friendship/friends.validation.tab_invalid'],
    'an unknown sort' => ['sort', 'oldest', 'friendship/friends.validation.sort_invalid'],
    'a filter longer than the maximum' => [
        'q',
        str_repeat('a', FriendshipIndexRequest::TERM_MAX_LENGTH + 1),
        'friendship/friends.validation.q_max',
    ],
]);

it('sorts :dataset by the name of the other person', function (FriendshipListTab $friendshipListTab): void {
    $user = createUser();

    foreach (['Carla', 'Ana', 'Bruno'] as $name) {
        createFriendsPageRow($user, $friendshipListTab, $name);
    }

    $testResponse = visitFriendsPageAs($this, $user, ['tab' => $friendshipListTab->value, 'sort' => 'name'])->assertOk();

    expect(friendsPageNames($testResponse))->toBe(['Ana', 'Bruno', 'Carla']);
    $testResponse->assertInertia(fn (AssertableInertia $assertableInertia): AssertableInertia => $assertableInertia
        ->where('filters.sort', FriendshipListSort::Name->value));
})->with([
    'the friends' => [FriendshipListTab::Friends],
    'the received requests' => [FriendshipListTab::Received],
    'the sent requests' => [FriendshipListTab::Sent],
]);

it('keeps the name sort on the next page without repeating or skipping people with the same name', function (): void {
    config(['module.friendship.list_page_size' => 2]);
    $user = createUser();
    $friendship = createFriendship($user, friendsPageUserNamed('Ana'));
    $secondAna = createFriendship(friendsPageUserNamed('Ana'), $user);
    $bruno = createFriendship(friendsPageUserNamed('Bruno'), $user);
    $carla = createFriendship($user, friendsPageUserNamed('Carla'));

    $testResponse = visitFriendsPageAs($this, $user, ['sort' => 'name'])->assertOk();

    /** @var array{scrollProps: array{friendships: array{pageName: string, nextPage: string}}} $page */
    $page = $testResponse->viewData('page');
    $scrollProps = $page['scrollProps']['friendships'];

    $secondPage = visitFriendsPageAs($this, $user, ['sort' => 'name', $scrollProps['pageName'] => $scrollProps['nextPage']])->assertOk();

    expect(array_column(friendsPageRows($testResponse), 'friendshipId'))->toBe([$friendship->id, $secondAna->id])
        ->and(array_column(friendsPageRows($secondPage), 'friendshipId'))->toBe([$bruno->id, $carla->id]);
});

it('counts the friends each person shares with the viewer', function (): void {
    $user = createUser();
    $friend = friendsPageUserNamed('Ana');
    createFriendship($friend, $user);
    $requester = friendsPageUserNamed('Bruno');
    createFriendRequest($requester, $user);

    $sharedWithBoth = createUser();
    createFriendship($user, $sharedWithBoth);
    createFriendship($sharedWithBoth, $friend);
    createFriendship($requester, $sharedWithBoth);

    $sharedWithFriend = createUser();
    createFriendship($sharedWithFriend, $user);
    createFriendship($friend, $sharedWithFriend);
    createFriendRequest($requester, $sharedWithFriend);

    $friendRows = friendsPageRows(visitFriendsPageAs($this, $user, ['q' => 'Ana'])->assertOk());
    $receivedRows = friendsPageRows(visitFriendsPageAs($this, $user, ['tab' => 'received'])->assertOk());

    expect(array_column($friendRows, 'mutualFriendsCount'))->toBe([2])
        ->and(array_column($receivedRows, 'mutualFriendsCount'))->toBe([1]);
});

it('runs the same queries for 3 or 15 rows sorted by :dataset', function (string $sort): void {
    $user = createUser();
    $commonFriend = createUser();
    createFriendship($user, $commonFriend);

    $createRows = function (int $count) use ($user, $commonFriend): void {
        foreach (range(1, $count) as $position) {
            $friend = createFriendship(createUser(), $user)->requester;
            createFriendship($friend, $commonFriend);
        }
    };

    $createRows(3);
    visitFriendsPageAs($this, $user, ['sort' => $sort]);
    $queriesForThreeRows = friendsPageQueriesWith($this, $user, ['sort' => $sort]);

    $createRows(12);
    $queriesForFifteenRows = friendsPageQueriesWith($this, $user, ['sort' => $sort]);

    expect($queriesForFifteenRows)->toBe($queriesForThreeRows);
})->with(['recent', 'name']);

it('refuses to serialize a row whose query did not select the mutual friends count', function (): void {
    $user = createUser();
    $friendship = createFriendship($user, createUser())->load(['requester.media', 'receiver.media']);

    $request = Request::create('/friendships');
    $request->setUserResolver(fn (): User => $user);

    expect(fn (): array => new FriendshipListItemResource($friendship)->toArray($request))
        ->toThrow(LogicException::class, 'mutual_friends_count');
});
