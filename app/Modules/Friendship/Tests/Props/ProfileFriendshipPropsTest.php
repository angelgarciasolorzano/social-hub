<?php

declare(strict_types=1);

use App\Modules\Friendship\Enums\FriendshipState;
use App\User\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * Visit someone else's profile and return its `profileFriendship` prop.
 *
 * @return array<string, mixed>
 */
function profileFriendshipOf(User $viewer, User $profileUser): array
{
    /** @var array<string, mixed> $profileFriendship */
    $profileFriendship = actingAs($viewer)
        ->get(route('profile.show', $profileUser))
        ->assertOk()
        ->inertiaProps('profileFriendship');

    return $profileFriendship;
}

it("shows :dataset on someone else's profile with the row id", function (FriendshipState $friendshipState): void {
    $user = createUser();
    $profileUser = createUser();
    $friendship = match ($friendshipState) {
        FriendshipState::None => null,
        FriendshipState::RequestSent => createFriendRequest($user, $profileUser),
        FriendshipState::RequestReceived => createFriendRequest($profileUser, $user),
        FriendshipState::Friends => createFriendship($profileUser, $user),
    };

    expect(profileFriendshipOf($user, $profileUser))
        ->toMatchArray([
            'isOwnProfile' => false,
            'friendshipState' => $friendshipState->value,
            'friendshipId' => $friendship?->id,
        ]);
})->with([
    'no friendship' => [FriendshipState::None],
    'a sent request' => [FriendshipState::RequestSent],
    'a received request' => [FriendshipState::RequestReceived],
    'a friendship' => [FriendshipState::Friends],
]);

it('counts the active friends both users share, and only accepted friendships', function (): void {
    $user = createUser();
    $profileUser = createUser();

    foreach (range(1, 2) as $index) {
        $sharedFriend = createUser();
        createFriendship($user, $sharedFriend);
        createFriendship($sharedFriend, $profileUser);
    }

    $pendingFriend = createUser();
    createFriendship($user, $pendingFriend);
    createFriendRequest($pendingFriend, $profileUser);

    $suspendedFriend = createSuspendedUser();
    createFriendship($user, $suspendedFriend);
    createFriendship($profileUser, $suspendedFriend);

    createFriendship($profileUser, createUser());

    expect(profileFriendshipOf($user, $profileUser))
        ->toMatchArray(['friendsCount' => 3, 'mutualFriendsCount' => 2]);
});

it('shows the real friend count and no relation on the own profile', function (string $routeName): void {
    $user = createUser();
    createFriendship($user, createUser());
    createFriendship(createUser(), $user);
    createFriendRequest(createUser(), $user);
    createFriendRequest($user, createUser());

    $this->actingAs($user)
        ->get($routeName === 'profile.index' ? route('profile.index') : route('profile.show', $user))
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert
            ->where('profileFriendship', [
                'friendsCount' => 2,
                'isOwnProfile' => true,
                'friendshipState' => null,
                'friendshipId' => null,
                'mutualFriendsCount' => 0,
            ]));
})->with(['profile.index', 'profile.show']);
