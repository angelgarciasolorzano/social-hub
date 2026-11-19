<?php

declare(strict_types=1);

namespace App\Modules\Friendship\Services;

use App\Modules\Friendship\Enums\FriendshipOperationResult;
use App\Modules\Friendship\Enums\FriendshipStatus;
use App\Modules\Friendship\Events\FriendRequestAccepted;
use App\Modules\Friendship\Events\FriendRequestSent;
use App\Modules\Friendship\Events\FriendshipEnded;
use App\Modules\Friendship\Models\FriendRequestDecline;
use App\Modules\Friendship\Models\Friendship;
use App\User\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class FriendshipService
{
    /**
     * Send a friend request, unless the pair already has a row, the receiver declined the
     * requester recently, the requester reached the pending limit or the receiver is suspended.
     *
     * @throws LogicException when both users are the same person
     */
    public function send(User $requester, User $receiver): FriendshipOperationResult
    {
        throw_if($requester->is($receiver), LogicException::class, 'A user cannot send a friend request to themselves.');

        return DB::transaction(fn (): FriendshipOperationResult => $this->sendWithLockedRequester($requester, $receiver));
    }

    /**
     * Accept a pending request received by the actor.
     *
     * @throws LogicException when the actor is not the receiver
     */
    public function accept(Friendship $friendship, User $user): FriendshipOperationResult
    {
        $friendshipOperationResult = DB::transaction(function () use ($friendship, $user): FriendshipOperationResult {
            $lockedFriendship = $this->lockedFriendship($friendship);

            if (! $lockedFriendship instanceof Friendship || ! $lockedFriendship->isPending()) {
                return FriendshipOperationResult::NoLongerPending;
            }

            $this->ensureActorIs($user, $lockedFriendship->receiver_id, 'Only the receiver can accept a friend request.');

            $lockedFriendship->forceFill([
                'status' => FriendshipStatus::Accepted,
                'accepted_at' => now(),
            ])->save();

            event(new FriendRequestAccepted($lockedFriendship));

            return FriendshipOperationResult::Accepted;
        });

        if ($friendshipOperationResult === FriendshipOperationResult::Accepted) {
            $friendship->refresh();
        }

        return $friendshipOperationResult;
    }

    /**
     * Decline a pending request received by the actor and start the requester's cooldown.
     *
     * @throws LogicException when the actor is not the receiver
     */
    public function decline(Friendship $friendship, User $user): FriendshipOperationResult
    {
        return DB::transaction(function () use ($friendship, $user): FriendshipOperationResult {
            $lockedFriendship = $this->lockedFriendship($friendship);

            if (! $lockedFriendship instanceof Friendship || ! $lockedFriendship->isPending()) {
                return FriendshipOperationResult::NoLongerPending;
            }

            $this->ensureActorIs($user, $lockedFriendship->receiver_id, 'Only the receiver can decline a friend request.');

            FriendRequestDecline::query()->updateOrCreate(
                ['requester_id' => $lockedFriendship->requester_id, 'receiver_id' => $lockedFriendship->receiver_id],
                ['declined_at' => now()],
            );

            return $this->end($lockedFriendship, $user, FriendshipOperationResult::Declined);
        });
    }

    /**
     * Cancel a pending request the actor sent; it starts no cooldown.
     *
     * @throws LogicException when the actor is not the requester
     */
    public function cancel(Friendship $friendship, User $user): FriendshipOperationResult
    {
        return DB::transaction(function () use ($friendship, $user): FriendshipOperationResult {
            $lockedFriendship = $this->lockedFriendship($friendship);

            if (! $lockedFriendship instanceof Friendship || ! $lockedFriendship->isPending()) {
                return FriendshipOperationResult::NoLongerPending;
            }

            $this->ensureActorIs($user, $lockedFriendship->requester_id, 'Only the requester can cancel a friend request.');

            return $this->end($lockedFriendship, $user, FriendshipOperationResult::Canceled);
        });
    }

    /**
     * Remove an accepted friendship of the actor, whoever sent the request; it starts no cooldown.
     *
     * @throws LogicException when the actor is not part of the friendship
     */
    public function remove(Friendship $friendship, User $user): FriendshipOperationResult
    {
        return DB::transaction(function () use ($friendship, $user): FriendshipOperationResult {
            $lockedFriendship = $this->lockedFriendship($friendship);

            if (! $lockedFriendship instanceof Friendship || ! $lockedFriendship->isAccepted()) {
                return FriendshipOperationResult::NoLongerPending;
            }

            throw_unless($lockedFriendship->involves($user), LogicException::class, 'Only a member of the friendship can remove it.');

            return $this->end($lockedFriendship, $user, FriendshipOperationResult::Removed);
        });
    }

    /**
     * Apply the rules of a new request with the requester's row locked, which serializes the
     * pending count of concurrent requests by the same person, and create the request.
     */
    private function sendWithLockedRequester(User $requester, User $receiver): FriendshipOperationResult
    {
        User::query()->lockForUpdate()->whereKey($requester->id)->firstOrFail();

        if (User::query()->whereKey($receiver->id)->firstOrFail()->isSuspended()) {
            return FriendshipOperationResult::ReceiverUnavailable;
        }

        $existingPairResult = $this->resultForExistingPair($requester, $receiver, lock: true);

        if ($existingPairResult instanceof FriendshipOperationResult) {
            return $existingPairResult;
        }

        if (FriendRequestDecline::query()->activeFor($requester, $receiver, Config::integer('module.friendship.decline_cooldown_days'))->exists()) {
            return FriendshipOperationResult::CooldownActive;
        }

        if (Friendship::query()->pending()->where('requester_id', $requester->id)->count() >= Config::integer('module.friendship.max_pending_sent')) {
            return FriendshipOperationResult::PendingLimitReached;
        }

        try {
            $friendship = Friendship::query()->create([
                'requester_id' => $requester->id,
                'receiver_id' => $receiver->id,
                'status' => FriendshipStatus::Pending,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->resultForRacedPair($requester, $receiver);
        }

        event(new FriendRequestSent($friendship));

        return FriendshipOperationResult::Sent;
    }

    /**
     * Explain why the pair cannot get a new request when it already has a row, or null when it has none.
     */
    private function resultForExistingPair(User $requester, User $receiver, bool $lock = false): ?FriendshipOperationResult
    {
        $pairQuery = Friendship::query()->between($requester, $receiver);

        if ($lock) {
            $pairQuery->lockForUpdate();
        }

        $existingFriendship = $pairQuery->first();

        return match (true) {
            ! $existingFriendship instanceof Friendship => null,
            $existingFriendship->isAccepted() => FriendshipOperationResult::AlreadyFriends,
            $existingFriendship->requester_id === $requester->id => FriendshipOperationResult::AlreadyPending,
            default => FriendshipOperationResult::IncomingRequestExists,
        };
    }

    /**
     * Explain the row another request inserted first. MySQL undoes only the failed insert, not the
     * transaction, and a locking read sees the row the other transaction committed.
     */
    private function resultForRacedPair(User $requester, User $receiver): FriendshipOperationResult
    {
        return $this->resultForExistingPair($requester, $receiver, lock: true)
            ?? FriendshipOperationResult::NoLongerPending;
    }

    /**
     * Read the row again with a lock: the bound model may be stale after a concurrent action.
     */
    private function lockedFriendship(Friendship $friendship): ?Friendship
    {
        return Friendship::query()->lockForUpdate()->find($friendship->id);
    }

    /**
     * Fail loudly when the policy let through an actor who is not the expected user of the row.
     *
     * @throws LogicException
     */
    private function ensureActorIs(User $user, int $expectedUserId, string $message): void
    {
        throw_unless($user->id === $expectedUserId, LogicException::class, $message);
    }

    /**
     * Delete the row and announce how it ended.
     */
    private function end(Friendship $friendship, User $user, FriendshipOperationResult $friendshipOperationResult): FriendshipOperationResult
    {
        $otherUser = User::query()->findOrFail($friendship->otherUserId($user));

        $friendship->delete();

        event(new FriendshipEnded($friendship->id, $user, $otherUser, $friendshipOperationResult));

        return $friendshipOperationResult;
    }
}
