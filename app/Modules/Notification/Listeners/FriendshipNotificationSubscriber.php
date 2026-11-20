<?php

declare(strict_types=1);

namespace App\Modules\Notification\Listeners;

use App\Modules\Friendship\Enums\FriendshipOperationResult;
use App\Modules\Friendship\Events\FriendRequestAccepted;
use App\Modules\Friendship\Events\FriendRequestSent;
use App\Modules\Friendship\Events\FriendshipEnded;
use App\Modules\Notification\Enums\NotificationType;
use App\Modules\Notification\Notifications\FriendRequestAcceptedNotification;
use App\Modules\Notification\Notifications\FriendRequestReceivedNotification;
use App\User\Models\User;

final readonly class FriendshipNotificationSubscriber
{
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

        $friendship->receiver->notify(new FriendRequestReceivedNotification(
            $friendship->requester->id,
            $friendship->requester->name,
            $friendship->id,
        ));
    }

    public function handleFriendRequestAccepted(FriendRequestAccepted $friendRequestAccepted): void
    {
        $friendship = $friendRequestAccepted->friendship;

        $friendship->requester->notify(new FriendRequestAcceptedNotification(
            $friendship->receiver->id,
            $friendship->receiver->name,
            $friendship->id,
        ));

        $this->deleteUnreadReceivedRequest($friendship->receiver, $friendship->id);
    }

    public function handleFriendshipEnded(FriendshipEnded $friendshipEnded): void
    {
        $receiver = match ($friendshipEnded->reason) {
            FriendshipOperationResult::Declined => $friendshipEnded->actor,
            FriendshipOperationResult::Canceled => $friendshipEnded->otherUser,
            default => null,
        };

        if ($receiver instanceof User) {
            $this->deleteUnreadReceivedRequest($receiver, $friendshipEnded->friendshipId);
        }
    }

    /**
     * Delete the unread "sent you a friend request" notification of the receiver, inside the
     * service's transaction, and broadcast each deletion once it commits. A read one stays, so
     * the unread count of the client never drops twice.
     */
    private function deleteUnreadReceivedRequest(User $user, int $friendshipId): void
    {
        $builder = $user->unreadNotifications()
            ->where('type', NotificationType::FriendRequestReceived->value);

        $builder->getQuery()->getQuery()->where('data->target->friendshipId', $friendshipId);

        foreach ($builder->get() as $databaseNotification) {
            $databaseNotification->delete();

            $user->notify(FriendRequestReceivedNotification::deletion($databaseNotification->id));
        }
    }
}
