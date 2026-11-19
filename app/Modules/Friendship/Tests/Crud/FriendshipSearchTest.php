<?php

declare(strict_types=1);

use App\Modules\Friendship\Enums\FriendshipState;
use App\Modules\Friendship\Requests\FriendshipSearchRequest;
use App\User\Enums\PreferredLocale;
use App\User\Enums\UserImageType;
use App\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Open the people search page as the given user, the way the browser does.
 *
 * @param  array<string, string>  $query
 * @return TestResponse<Response>
 */
function searchPeopleAs(TestCase $testCase, User $user, array $query): TestResponse
{
    return $testCase->actingAs($user)->get(route('friendships.search', $query));
}

/**
 * Create a user with the given name.
 */
function createUserNamed(string $name): User
{
    $user = createUser();
    $user->forceFill(['name' => $name])->save();

    return $user;
}

/**
 * The search results the page received.
 *
 * @param  TestResponse<Response>  $testResponse
 * @return list<array{id: int, name: string, profilePicture: string, friendshipState: string, friendshipId: int|null, mutualFriendsCount: int}>
 */
function searchResultsOf(TestResponse $testResponse): array
{
    $page = $testResponse->viewData('page');

    expect($page)->toBeArray();

    /** @var array{props: array{searchResults: array{data: list<array{id: int, name: string, profilePicture: string, friendshipState: string, friendshipId: int|null, mutualFriendsCount: int}>}}} $page */
    return $page['props']['searchResults']['data'];
}

/**
 * The names of the people the page lists, in order.
 *
 * @param  TestResponse<Response>  $testResponse
 * @return list<string>
 */
function searchResultNames(TestResponse $testResponse): array
{
    return array_column(searchResultsOf($testResponse), 'name');
}

it('renders the search page with the term and each person with only their public data', function (): void {
    $user = createUser();
    $friend = createUserNamed('Ana Amiga');
    $friendship = createFriendship($user, $friend);

    $testResponse = searchPeopleAs($this, $user, ['q' => 'Ana'])->assertOk();

    $testResponse->assertInertia(fn (AssertableInertia $assertableInertia): AssertableInertia => $assertableInertia
        ->component('friendship/Search')
        ->where('filters.q', 'Ana')
        ->where('searchTermMinLength', FriendshipSearchRequest::TERM_MIN_LENGTH));

    expect(searchResultsOf($testResponse))->toBe([[
        'id' => $friend->id,
        'name' => 'Ana Amiga',
        'profilePicture' => $friend->getFirstMediaUrl(UserImageType::PROFILE_PICTURE->value()),
        'friendshipState' => FriendshipState::Friends->value,
        'friendshipId' => $friendship->id,
        'mutualFriendsCount' => 0,
    ]]);
});

it('never exposes the email of the people found', function (): void {
    $user = createUser();
    $person = createUserNamed('Ana Persona');

    $testResponse = searchPeopleAs($this, $user, ['q' => 'Ana'])->assertOk();

    expect(json_encode($testResponse->viewData('page')))->not->toContain($person->email)
        ->and(searchResultsOf($testResponse)[0])->not->toHaveKey('email');
});

it('tells the viewer how they relate to each person', function (): void {
    $user = createUser();
    $sentTo = createUserNamed('Ana Enviada');
    $receivedFrom = createUserNamed('Ana Recibida');
    createUserNamed('Ana Desconocida');
    $friendship = createFriendRequest($user, $sentTo);
    $receivedRequest = createFriendRequest($receivedFrom, $user);

    $searchResults = searchResultsOf(searchPeopleAs($this, $user, ['q' => 'Ana'])->assertOk());

    expect(array_column($searchResults, 'friendshipState'))->toBe([
        FriendshipState::None->value,
        FriendshipState::RequestSent->value,
        FriendshipState::RequestReceived->value,
    ])->and(array_column($searchResults, 'friendshipId'))->toBe([null, $friendship->id, $receivedRequest->id]);
});

it('counts the friends the viewer and each person have in common', function (): void {
    $user = createUser();
    $person = createUserNamed('Ana Persona');
    $firstCommonFriend = createUser();
    $secondCommonFriend = createUser();
    createFriendship($user, $firstCommonFriend);
    createFriendship($secondCommonFriend, $user);
    createFriendship($person, $firstCommonFriend);
    createFriendship($secondCommonFriend, $person);
    createFriendship($person);
    createFriendRequest($user, createUser());

    expect(searchResultsOf(searchPeopleAs($this, $user, ['q' => 'Ana'])->assertOk())[0]['mutualFriendsCount'])->toBe(2);
});

it('leaves out suspended accounts and the viewer', function (): void {
    $user = createUserNamed('Ana Viewer');
    $suspended = createSuspendedUser();
    $suspended->forceFill(['name' => 'Ana Suspendida'])->save();
    createUserNamed('Ana Activa');

    expect(searchResultNames(searchPeopleAs($this, $user, ['q' => 'Ana'])->assertOk()))->toBe(['Ana Activa']);
});

it('matches the name from its start, ignoring case', function (): void {
    $user = createUser();
    createUserNamed('Ana Inicio');
    createUserNamed('Mariana Medio');

    expect(searchResultNames(searchPeopleAs($this, $user, ['q' => 'ana'])->assertOk()))->toBe(['Ana Inicio']);
});

it('treats :dataset in the search as a literal character', function (string $term, array $expectedNames): void {
    $user = createUser();
    createUserNamed('A%na');
    createUserNamed('A_na');
    createUserNamed('Abc');

    expect(searchResultNames(searchPeopleAs($this, $user, ['q' => $term])->assertOk()))->toBe($expectedNames);
})->with([
    'a percent sign' => ['A%', ['A%na']],
    'an underscore' => ['A_', ['A_na']],
]);

it('loads the next page with the cursor the infinite scroll sends, keeping the search', function (): void {
    config(['module.friendship.search_page_size' => 2]);
    $user = createUser();
    createUserNamed('Ana Uno');
    createUserNamed('Ana Dos');
    createUserNamed('Ana Tres');

    $testResponse = searchPeopleAs($this, $user, ['q' => 'Ana'])->assertOk();

    /** @var array{scrollProps: array{searchResults: array{pageName: string, nextPage: string}}} $page */
    $page = $testResponse->viewData('page');
    $scrollProps = $page['scrollProps']['searchResults'];

    $secondPage = searchPeopleAs($this, $user, ['q' => 'Ana', $scrollProps['pageName'] => $scrollProps['nextPage']])->assertOk();

    expect(searchResultNames($testResponse))->toBe(['Ana Dos', 'Ana Tres'])
        ->and(searchResultNames($secondPage))->toBe(['Ana Uno']);
});

it('lists nobody without querying people when the term is :dataset', function (string $term): void {
    $user = createUser();
    createUserNamed('Ana Persona');
    searchPeopleAs($this, $user, []);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $testResponse = searchPeopleAs($this, $user, ['q' => $term])->assertOk();
    $peopleQueries = array_filter(
        DB::getQueryLog(),
        fn (array $query): bool => str_contains($query['query'], 'from `users`') && str_contains($query['query'], 'like'),
    );

    DB::disableQueryLog();

    expect(searchResultsOf($testResponse))->toBeEmpty()
        ->and($peopleQueries)->toBeEmpty();
})->with([
    'empty' => [''],
    'one character long' => ['A'],
]);

it('rejects a term longer than the maximum', function (): void {
    $expectedMessage = str_replace(
        ':max',
        (string) FriendshipSearchRequest::TERM_MAX_LENGTH,
        translatedTextIn(PreferredLocale::ENGLISH, 'friendship/actions.validation.search_max'),
    );

    searchPeopleAs($this, createUser(), ['q' => str_repeat('a', FriendshipSearchRequest::TERM_MAX_LENGTH + 1)])
        ->assertSessionHasErrors(['q' => $expectedMessage]);
});
