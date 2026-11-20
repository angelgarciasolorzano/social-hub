<?php

declare(strict_types=1);

namespace App\Modules\ActivityLog\Listeners;

use App\Modules\ActivityLog\Enums\Events\ActivityLogFriendshipEvent;
use App\Modules\ActivityLog\Services\ActivityLogger;
use App\Modules\Friendship\Enums\FriendshipOperationResult;
use App\Modules\Friendship\Events\FriendRequestAccepted;
use App\Modules\Friendship\Events\FriendRequestSent;
use App\Modules\Friendship\Events\FriendshipEnded;
use App\User\Models\User;

final readonly class FriendshipActivitySubscriber
{
    public function __construct(private ActivityLogger $activityLogger) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            FriendRequestSent::class => 'handleFriendRequestSent',
            FriendRequestAccepted::class => 'handleFriendRequestAccepted',
            FriendshipEnded::class => 'handleFriendshipEnded',
        ];
    }

    public function handleFriendRequestSent(FriendRequestSent $friendRequestSent): void
    {
        $friendship = $friendRequestSent->friendship;

        $this->record(ActivityLogFriendshipEvent::RequestSent, $friendship->requester, $friendship->id, $friendship->receiver_id);
    }

    public function handleFriendRequestAccepted(FriendRequestAccepted $friendRequestAccepted): void
    {
        $friendship = $friendRequestAccepted->friendship;

        $this->record(ActivityLogFriendshipEvent::RequestAccepted, $friendship->receiver, $friendship->id, $friendship->requester_id);
    }

    public function handleFriendshipEnded(FriendshipEnded $friendshipEnded): void
    {
        $activityLogFriendshipEvent = match ($friendshipEnded->reason) {
            FriendshipOperationResult::Declined => ActivityLogFriendshipEvent::RequestDeclined,
            FriendshipOperationResult::Canceled => ActivityLogFriendshipEvent::RequestCanceled,
            default => ActivityLogFriendshipEvent::Removed,
        };

        $this->record($activityLogFriendshipEvent, $friendshipEnded->actor, $friendshipEnded->friendshipId, $friendshipEnded->otherUser->id);
    }

    private function record(ActivityLogFriendshipEvent $activityLogFriendshipEvent, User $user, int $friendshipId, int $targetUserId): void
    {
        $this->activityLogger->record($activityLogFriendshipEvent, causer: $user, properties: [
            'friendship_id' => $friendshipId,
            'target_user_id' => $targetUserId,
        ]);
    }
}
