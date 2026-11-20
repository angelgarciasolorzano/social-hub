<?php

declare(strict_types=1);

use App\Modules\Friendship\Models\Friendship;
use App\Modules\Friendship\Queries\MutualFriendsQuery;
use App\User\Models\User;

/**
 * The mutual friends of the viewer and `$otherUser`, counted from the user's own row.
 */
function mutualFriendsCountThroughUserColumn(User $viewer, User $otherUser): int
{
    $mutualFriendsCount = User::query()
        ->whereKey($otherUser->id)
        ->select(['mutual_friends_count' => new MutualFriendsQuery()->countFor($viewer, 'users.id')])
        ->toBase()
        ->value('mutual_friends_count');

    return is_numeric($mutualFriendsCount) ? (int) $mutualFriendsCount : -1;
}

/**
 * The mutual friends of the requester and the receiver of `$friendship`, counted from the friendship row,
 * whose own `friendships.*` columns must not clash with the counted rows.
 */
function mutualFriendsCountThroughFriendshipRow(User $user, Friendship $friendship): int
{
    $mutualFriendsCount = Friendship::query()
        ->whereKey($friendship->id)
        ->select(['mutual_friends_count' => new MutualFriendsQuery()->countFor($user, 'friendships.receiver_id')])
        ->toBase()
        ->value('mutual_friends_count');

    return is_numeric($mutualFriendsCount) ? (int) $mutualFriendsCount : -1;
}

/**
 * The viewer, the other user and the friendship between them, with two friends in common (one on
 * each side of the pair), one friend of the viewer whose request to the other user is still pending
 * and one friend of the other user only.
 *
 * @return array{viewer: User, otherUser: User, friendship: Friendship}
 */
function mutualFriendsScenario(): array
{
    $user = createUser();
    $otherUser = createUser();
    $friendship = createFriendship($user, $otherUser);

    $commonAsReceiver = createUser();
    createFriendship($user, $commonAsReceiver);
    createFriendship($otherUser, $commonAsReceiver);

    $commonAsRequester = createUser();
    createFriendship($commonAsRequester, $user);
    createFriendship($commonAsRequester, $otherUser);

    $pendingWithOtherUser = createUser();
    createFriendship($user, $pendingWithOtherUser);
    createFriendRequest($pendingWithOtherUser, $otherUser);

    createFriendship($otherUser, createUser());

    return ['viewer' => $user, 'otherUser' => $otherUser, 'friendship' => $friendship];
}

it('counts only the accepted friends both users share', function (): void {
    ['viewer' => $viewer, 'otherUser' => $otherUser] = mutualFriendsScenario();

    expect(mutualFriendsCountThroughUserColumn($viewer, $otherUser))->toBe(2);
});

it('reads the other user from a column of an outer friendships row', function (): void {
    ['viewer' => $viewer, 'friendship' => $friendship] = mutualFriendsScenario();

    expect(mutualFriendsCountThroughFriendshipRow($viewer, $friendship))->toBe(2);
});

it('counts zero for users without friends in common', function (): void {
    $user = createUser();
    $otherUser = createUser();
    createFriendship($user, createUser());
    createFriendship($otherUser, createUser());

    expect(mutualFriendsCountThroughUserColumn($user, $otherUser))->toBe(0);
});

it('lists the friends of the viewer on both sides of the pair, without pending requests', function (): void {
    $user = createUser();
    $receiverFriend = createUser();
    $requesterFriend = createUser();
    createFriendship($user, $receiverFriend);
    createFriendship($requesterFriend, $user);
    createFriendRequest($user, createUser());

    expect(new MutualFriendsQuery()->friendIdsOf($user)->pluck('receiver_id')->all())
        ->toEqualCanonicalizing([$receiverFriend->id, $requesterFriend->id]);
});
