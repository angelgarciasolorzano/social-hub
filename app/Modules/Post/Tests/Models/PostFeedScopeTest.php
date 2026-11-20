<?php

declare(strict_types=1);

use App\Modules\Post\Models\Post;
use App\User\Models\User;

/**
 * The ids of the posts in the home feed of the given user, oldest first.
 *
 * @return list<int>
 */
function feedPostIdsOf(User $user): array
{
    return array_values(Post::query()->visibleInFeedOf($user)->orderBy('id')->get()
        ->map(fn (Post $post): int => $post->id)
        ->all());
}

it('shows the own posts and the posts of accepted friends from both sides of the pair', function (): void {
    $user = createUser();
    $friendWhoReceived = createUser();
    $friendWhoSent = createUser();
    createFriendship($user, $friendWhoReceived);
    createFriendship($friendWhoSent, $user);

    $ownPost = createPost($user);
    $receivedFriendPost = createPost($friendWhoReceived);
    $sentFriendPost = createPost($friendWhoSent);

    expect(feedPostIdsOf($user))->toBe([$ownPost->id, $receivedFriendPost->id, $sentFriendPost->id]);
});

it('hides the posts of strangers, pending requests in both directions, removed friends and suspended friends', function (): void {
    $user = createUser();
    $sentRequestReceiver = createUser();
    $receivedRequestSender = createUser();
    $removedFriend = createUser();
    $suspendedFriend = createSuspendedUser();
    createFriendRequest($user, $sentRequestReceiver);
    createFriendRequest($receivedRequestSender, $user);
    createFriendship($user, $suspendedFriend);
    createFriendship($user, $removedFriend)->delete();

    createPost();
    createPost($sentRequestReceiver);
    createPost($receivedRequestSender);
    createPost($removedFriend);
    createPost($suspendedFriend);
    $ownPost = createPost($user);

    expect(feedPostIdsOf($user))->toBe([$ownPost->id]);
});

it('does not show a friend the posts of the friends of their friend', function (): void {
    $user = createUser();
    $friend = createUser();
    $friendOfFriend = createUser();
    createFriendship($user, $friend);
    createFriendship($friend, $friendOfFriend);

    $friendPost = createPost($friend);
    createPost($friendOfFriend);

    expect(feedPostIdsOf($user))->toBe([$friendPost->id]);
});

it('reads the friends with a subquery instead of loading their ids', function (): void {
    $user = createUser();
    createFriendship($user, createUser());

    $sql = Post::query()->visibleInFeedOf($user)->toSql();

    expect($sql)->toContain('from `friendships`')
        ->and($sql)->toContain('`posts`.`user_id` in (select');
});
