<?php

declare(strict_types=1);

use App\Modules\Friendship\Enums\FriendshipState;
use App\Modules\Friendship\Requests\FriendshipSearchRequest;
use App\User\Enums\PreferredLocale;
use App\User\Enums\UserImageType;
use App\User\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Search people as the given user, the way the frontend does.
 *
 * @param  array<string, string>  $query
 * @return TestResponse<Response>
 */
function searchPeopleAs(TestCase $testCase, User $user, array $query): TestResponse
{
    return $testCase->actingAs($user)->getJson(route('friendships.search', $query));
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
 * The names of the people a search response lists, in order.
 *
 * @param  TestResponse<Response>  $testResponse
 * @return list<string>
 */
function searchResultNames(TestResponse $testResponse): array
{
    /** @var list<array{name: string}> $results */
    $results = $testResponse->json('data');

    return array_column($results, 'name');
}

it('answers each person with only their public data and the friendship state', function (): void {
    $user = createUser();
    $friend = createUserNamed('Ana Amiga');
    $friendship = createFriendship($user, $friend);

    searchPeopleAs($this, $user, ['q' => 'Ana'])
        ->assertOk()
        ->assertExactJson([
            'data' => [[
                'id' => $friend->id,
                'name' => 'Ana Amiga',
                'profilePicture' => $friend->getFirstMediaUrl(UserImageType::PROFILE_PICTURE->value()),
                'friendshipState' => FriendshipState::Friends->value,
                'friendshipId' => $friendship->id,
                'mutualFriendsCount' => 0,
            ]],
            'path' => route('friendships.search'),
            'per_page' => 15,
            'next_cursor' => null,
            'next_page_url' => null,
            'prev_cursor' => null,
            'prev_page_url' => null,
        ]);
});

it('never exposes the email of the people found', function (): void {
    $user = createUser();
    $person = createUserNamed('Ana Persona');

    searchPeopleAs($this, $user, ['q' => 'Ana'])
        ->assertOk()
        ->assertDontSee($person->email)
        ->assertJsonMissingPath('data.0.email');
});

it('tells the viewer how they relate to each person', function (): void {
    $user = createUser();
    $sentTo = createUserNamed('Ana Enviada');
    $receivedFrom = createUserNamed('Ana Recibida');
    createUserNamed('Ana Desconocida');
    $friendship = createFriendRequest($user, $sentTo);
    $receivedRequest = createFriendRequest($receivedFrom, $user);

    $testResponse = searchPeopleAs($this, $user, ['q' => 'Ana'])->assertOk();

    expect($testResponse->json('data.*.friendshipState'))->toBe([
        FriendshipState::None->value,
        FriendshipState::RequestSent->value,
        FriendshipState::RequestReceived->value,
    ])->and($testResponse->json('data.*.friendshipId'))->toBe([null, $friendship->id, $receivedRequest->id]);
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

    searchPeopleAs($this, $user, ['q' => 'Ana'])
        ->assertOk()
        ->assertJsonPath('data.0.mutualFriendsCount', 2);
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

it('pages the results with a cursor that keeps the search', function (): void {
    config(['module.friendship.search_page_size' => 2]);
    $user = createUser();
    createUserNamed('Ana Uno');
    createUserNamed('Ana Dos');
    createUserNamed('Ana Tres');

    $testResponse = searchPeopleAs($this, $user, ['q' => 'Ana'])->assertOk();

    /** @var string $nextPageUrl */
    $nextPageUrl = $testResponse->json('next_page_url');
    $secondPage = $this->actingAs($user)->getJson($nextPageUrl)->assertOk();

    expect(searchResultNames($testResponse))->toBe(['Ana Dos', 'Ana Tres'])
        ->and($nextPageUrl)->toContain('q=Ana')
        ->and(searchResultNames($secondPage))->toBe(['Ana Uno'])
        ->and($secondPage->json('next_page_url'))->toBeNull();
});

it('rejects a search without a term', function (): void {
    searchPeopleAs($this, createUser(), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['q' => translatedTextIn(PreferredLocale::ENGLISH, 'friendship/actions.validation.search_required')]);
});

it('rejects a search shorter than the minimum', function (): void {
    $expectedMessage = str_replace(
        ':min',
        (string) FriendshipSearchRequest::TERM_MIN_LENGTH,
        translatedTextIn(PreferredLocale::ENGLISH, 'friendship/actions.validation.search_min'),
    );

    searchPeopleAs($this, createUser(), ['q' => 'a'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['q' => $expectedMessage]);
});
