<?php

declare(strict_types=1);

use App\User\Models\User;
use Illuminate\Support\Facades\DB;

it('lists the accepted friends whoever sent the request', function (): void {
    $user = createUser();
    $friendWhoAsked = createUser();
    $friendAsked = createUser();
    createFriendship($friendWhoAsked, $user);
    createFriendship($user, $friendAsked);
    createFriendship();

    expect($user->friends()->orderBy('id')->pluck('id')->all())
        ->toBe([$friendWhoAsked->id, $friendAsked->id]);
});

it('does not list pending requests as friends', function (): void {
    $user = createUser();
    createFriendRequest($user);
    createFriendRequest(createUser(), $user);

    expect($user->friends()->exists())->toBeFalse();
});

it('separates the pending requests the user sent from the ones received', function (): void {
    $user = createUser();
    $friendship = createFriendRequest($user);
    $receivedRequest = createFriendRequest(createUser(), $user);
    createFriendship($user);

    expect($user->sentFriendRequests()->pluck('id')->all())->toBe([$friendship->id])
        ->and($user->receivedFriendRequests()->pluck('id')->all())->toBe([$receivedRequest->id]);
});

it('finds the row of two users from either side, and none between strangers', function (): void {
    $user = createUser();
    $otherUser = createUser();
    $friendship = createFriendRequest($user, $otherUser);

    expect($user->friendshipWith($otherUser)?->is($friendship))->toBeTrue()
        ->and($otherUser->friendshipWith($user)?->is($friendship))->toBeTrue()
        ->and($user->friendshipWith(createUser()))->toBeNull();
});

it('counts friends and received requests the same with and without preloading', function (): void {
    $user = createUser();
    createFriendship($user);
    createFriendship(createUser(), $user);
    createFriendRequest(createUser(), $user);
    createFriendRequest($user);

    $preloadedUser = User::query()->withFriendshipCounts()->findOrFail($user->id);

    expect($preloadedUser->friendsCount())->toBe(2)
        ->and($preloadedUser->pendingReceivedFriendRequestsCount())->toBe(1)
        ->and($user->friendsCount())->toBe(2)
        ->and($user->pendingReceivedFriendRequestsCount())->toBe(1);
});

it('reads the preloaded counts without running a query per user', function (): void {
    $users = array_map(static fn (int $position): User => createUser(), range(1, 5));

    foreach ($users as $user) {
        createFriendship($user);
        createFriendRequest(createUser(), $user);
    }

    $preloadedUsers = User::query()->withFriendshipCounts()->whereKey(array_column($users, 'id'))->get();

    DB::enableQueryLog();

    $counts = $preloadedUsers->map(static fn (User $user): array => [
        $user->friendsCount(),
        $user->pendingReceivedFriendRequestsCount(),
    ])->all();

    expect(DB::getQueryLog())
        ->toBeEmpty()
        ->and($counts)->toBe(array_fill(0, 5, [1, 1]));
});
