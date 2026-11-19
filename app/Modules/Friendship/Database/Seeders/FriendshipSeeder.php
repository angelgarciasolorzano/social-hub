<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Database\Seeders;

use App\Modules\Friendship\Enums\FriendshipStatus;
use App\Modules\Friendship\Models\FriendRequestDecline;
use App\Modules\Friendship\Models\Friendship;
use App\Support\Seeding\SkewedRandom;
use App\Support\Seeding\WeightedRandom;
use App\User\Database\Seeders\UserSeeder;
use App\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class FriendshipSeeder extends Seeder
{
    private const int MAX_FRIENDS_PER_USER = 25;

    private const float FRIENDS_SKEW = 2.0;

    private const int MAX_SENT_REQUESTS_PER_USER = 5;

    private const float SENT_REQUESTS_SKEW = 2.0;

    private const int DECLINES_SHARE_PERCENT = 5;

    private const int HISTORY_DAYS = 60;

    private const int TEST_USER_FRIENDS = 5;

    private const int TEST_USER_RECEIVED_REQUESTS = 3;

    private const int TEST_USER_SENT_REQUESTS = 2;

    private const int ROWS_PER_INSERT = 1000;

    /**
     * The pairs that already have a row, keyed "lowest id-highest id".
     *
     * @var array<string, true>
     */
    private array $usedPairs = [];

    /**
     * @var list<array{requester_id: int, receiver_id: int, status: string, accepted_at: CarbonImmutable|null, created_at: CarbonImmutable, updated_at: CarbonImmutable}>
     */
    private array $friendshipRows = [];

    /**
     * @var list<array{requester_id: int, receiver_id: int, declined_at: CarbonImmutable}>
     */
    private array $declineRows = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Friendship::query()->exists()) {
            return;
        }

        /** @var list<int> $userIds */
        $userIds = User::query()->pluck('id')->all();

        if (\count($userIds) < 2) {
            return;
        }

        $testUserId = User::query()->where('email', UserSeeder::TEST_USER_EMAIL)->value('id');

        if (\is_int($testUserId)) {
            $this->seedTestUserExamples($testUserId, $userIds);
        }

        foreach ($userIds as $userId) {
            $this->seedRandomRowsOf($userId, $userIds);
        }

        foreach (array_chunk($this->friendshipRows, self::ROWS_PER_INSERT) as $friendshipRows) {
            Friendship::query()->insertOrIgnore($friendshipRows);
        }

        foreach (array_chunk($this->declineRows, self::ROWS_PER_INSERT) as $declineRows) {
            FriendRequestDecline::query()->insertOrIgnore($declineRows);
        }
    }

    /**
     * Give the test user friends, received and sent requests, and a request they declined
     * a day ago, so every tab and the cooldown can be tried after signing in.
     *
     * @param  list<int>  $userIds
     */
    private function seedTestUserExamples(int $testUserId, array $userIds): void
    {
        $otherUserIds = array_values(array_diff($userIds, [$testUserId]));
        shuffle($otherUserIds);

        $examples = [
            ...array_fill(0, self::TEST_USER_FRIENDS, 'friend'),
            ...array_fill(0, self::TEST_USER_RECEIVED_REQUESTS, 'received'),
            ...array_fill(0, self::TEST_USER_SENT_REQUESTS, 'sent'),
            'declined',
        ];

        foreach (array_slice($examples, 0, \count($otherUserIds)) as $position => $example) {
            $otherUserId = $otherUserIds[$position];

            match ($example) {
                'friend' => $this->addFriendship($otherUserId, $testUserId),
                'received' => $this->addRequest($otherUserId, $testUserId),
                'sent' => $this->addRequest($testUserId, $otherUserId),
                default => $this->addDecline($otherUserId, $testUserId, now()->toImmutable()->subDay()),
            };
        }
    }

    /**
     * Send a skewed number of requests from the user, most of them already accepted, and
     * sometimes record a request somebody declined.
     *
     * @param  list<int>  $userIds
     */
    private function seedRandomRowsOf(int $userId, array $userIds): void
    {
        $otherUserIds = array_values(array_diff($userIds, [$userId]));

        $friendsCount = min(SkewedRandom::count(0, self::MAX_FRIENDS_PER_USER, self::FRIENDS_SKEW), \count($otherUserIds));
        $sentRequestsCount = min(SkewedRandom::count(0, self::MAX_SENT_REQUESTS_PER_USER, self::SENT_REQUESTS_SKEW), \count($otherUserIds));

        foreach ($this->randomUserIds($otherUserIds, $friendsCount) as $otherUserId) {
            $this->addFriendship($userId, $otherUserId);
        }

        foreach ($this->randomUserIds($otherUserIds, $sentRequestsCount) as $otherUserId) {
            $this->addRequest($userId, $otherUserId);
        }

        if (WeightedRandom::chance(self::DECLINES_SHARE_PERCENT)) {
            $this->addDecline($userId, $this->randomUserIds($otherUserIds, 1)[0], $this->randomPastDate());
        }
    }

    /**
     * Add an accepted friendship unless the pair already has a row.
     */
    private function addFriendship(int $requesterId, int $receiverId): void
    {
        if (! $this->claimPair($requesterId, $receiverId)) {
            return;
        }

        $createdAt = $this->randomPastDate();
        $acceptedAt = $createdAt->addMinutes(mt_rand(1, (int) $createdAt->diffInMinutes(now())));

        $this->friendshipRows[] = [
            'requester_id' => $requesterId,
            'receiver_id' => $receiverId,
            'status' => FriendshipStatus::Accepted->value,
            'accepted_at' => $acceptedAt,
            'created_at' => $createdAt,
            'updated_at' => $acceptedAt,
        ];
    }

    /**
     * Add a pending request unless the pair already has a row.
     */
    private function addRequest(int $requesterId, int $receiverId): void
    {
        if (! $this->claimPair($requesterId, $receiverId)) {
            return;
        }

        $createdAt = $this->randomPastDate();

        $this->friendshipRows[] = [
            'requester_id' => $requesterId,
            'receiver_id' => $receiverId,
            'status' => FriendshipStatus::Pending->value,
            'accepted_at' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    /**
     * Record that the receiver declined the requester, unless the pair already has a row:
     * a declined request leaves no friendship behind.
     */
    private function addDecline(int $requesterId, int $receiverId, CarbonImmutable $declinedAt): void
    {
        if (! $this->claimPair($requesterId, $receiverId)) {
            return;
        }

        $this->declineRows[] = [
            'requester_id' => $requesterId,
            'receiver_id' => $receiverId,
            'declined_at' => $declinedAt,
        ];
    }

    /**
     * Reserve the pair for one row, whichever user comes first; false when it is already taken.
     */
    private function claimPair(int $firstUserId, int $secondUserId): bool
    {
        $pairKey = min($firstUserId, $secondUserId).'-'.max($firstUserId, $secondUserId);

        if ($firstUserId === $secondUserId || isset($this->usedPairs[$pairKey])) {
            return false;
        }

        $this->usedPairs[$pairKey] = true;

        return true;
    }

    /**
     * Pick the given number of distinct user ids at random.
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    private function randomUserIds(array $userIds, int $count): array
    {
        shuffle($userIds);

        return array_slice($userIds, 0, $count);
    }

    /**
     * A moment within the history window, at least an hour ago.
     */
    private function randomPastDate(): CarbonImmutable
    {
        return now()->toImmutable()->subMinutes(mt_rand(60, self::HISTORY_DAYS * 24 * 60));
    }
}
