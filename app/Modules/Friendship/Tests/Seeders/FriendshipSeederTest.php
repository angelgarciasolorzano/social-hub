<?php

declare(strict_types=1);

use App\Modules\Friendship\Database\Seeders\FriendshipSeeder;
use App\Modules\Friendship\Models\FriendRequestDecline;
use App\Modules\Friendship\Models\Friendship;
use App\User\Database\Seeders\UserSeeder;
use App\User\Models\User;
use Tests\TestCase;

/**
 * Create the test user and the given number of other users, then run the seeder.
 */
function seedFriendshipsAmong(TestCase $testCase, int $otherUsersCount = 30): User
{
    $testUser = createUser();
    $testUser->forceFill(['email' => UserSeeder::TEST_USER_EMAIL])->save();

    foreach (range(1, $otherUsersCount) as $position) {
        createUser();
    }

    $testCase->seed(FriendshipSeeder::class);

    return $testUser;
}

/**
 * The pair key of a row or a decline, the same whoever sent the request.
 */
function friendshipPairKey(int $firstUserId, int $secondUserId): string
{
    return min($firstUserId, $secondUserId).'-'.max($firstUserId, $secondUserId);
}

it('never relates a user to themselves nor repeats a pair', function (): void {
    seedFriendshipsAmong($this);

    $pairKeys = Friendship::query()->get()->map(
        fn (Friendship $friendship): string => friendshipPairKey($friendship->requester_id, $friendship->receiver_id),
    );

    expect(Friendship::query()->whereColumn('requester_id', 'receiver_id')->exists())->toBeFalse()
        ->and($pairKeys->duplicates())->toBeEmpty();
});

it('keeps the declines apart from the pairs that have a row', function (): void {
    seedFriendshipsAmong($this);

    $friendshipPairs = Friendship::query()->get()->map(
        fn (Friendship $friendship): string => friendshipPairKey($friendship->requester_id, $friendship->receiver_id),
    );
    $declinePairs = FriendRequestDecline::query()->get()->map(
        fn (FriendRequestDecline $friendRequestDecline): string => friendshipPairKey($friendRequestDecline->requester_id, $friendRequestDecline->receiver_id),
    );

    expect($declinePairs->intersect($friendshipPairs))->toBeEmpty();
});

it('dates every row in the past, accepting a friendship after it was requested', function (): void {
    seedFriendshipsAmong($this);

    $now = now();

    expect(Friendship::query()->accepted()->whereNull('accepted_at')->exists())->toBeFalse()
        ->and(Friendship::query()->accepted()->whereColumn('accepted_at', '<', 'created_at')->exists())->toBeFalse()
        ->and(Friendship::query()->pending()->whereNotNull('accepted_at')->exists())->toBeFalse()
        ->and(Friendship::query()->where('created_at', '>', $now)->orWhere('accepted_at', '>', $now)->exists())->toBeFalse()
        ->and(FriendRequestDecline::query()->where('declined_at', '>', $now)->exists())->toBeFalse();
});

it('gives the test user friends, requests in both directions and a recent decline', function (): void {
    $testUser = seedFriendshipsAmong($this);

    expect($testUser->friends()->count())->toBeGreaterThanOrEqual(1)
        ->and($testUser->receivedFriendRequests()->count())->toBeGreaterThanOrEqual(1)
        ->and($testUser->sentFriendRequests()->count())->toBeGreaterThanOrEqual(1)
        ->and(FriendRequestDecline::query()->where('receiver_id', $testUser->id)->where('declined_at', '>', now()->subDays(7))->exists())->toBeTrue();
});

it('keeps every user below the limit of pending requests', function (): void {
    seedFriendshipsAmong($this);

    $pendingSentPerUser = Friendship::query()->pending()->toBase()
        ->selectRaw('requester_id, count(*) as total')
        ->groupBy('requester_id')
        ->pluck('total');

    expect($pendingSentPerUser->max() ?? 0)->toBeLessThanOrEqual(config()->integer('module.friendship.max_pending_sent'));
});

it('does not give every user the same number of friends', function (): void {
    seedFriendshipsAmong($this);

    $friendCounts = User::query()->withFriendshipCounts()->get()->map(fn (User $user): int => $user->friendsCount());

    expect($friendCounts->unique()->count())->toBeGreaterThan(1);
});

it('does nothing when it runs a second time', function (): void {
    seedFriendshipsAmong($this);
    $friendshipsCount = Friendship::query()->count();
    $declinesCount = FriendRequestDecline::query()->count();

    $this->seed(FriendshipSeeder::class);

    expect(Friendship::query()->count())->toBe($friendshipsCount)
        ->and(FriendRequestDecline::query()->count())->toBe($declinesCount);
});

it('does nothing without at least two users', function (): void {
    createUser();

    $this->seed(FriendshipSeeder::class);

    expect(Friendship::query()->exists())->toBeFalse();
});
