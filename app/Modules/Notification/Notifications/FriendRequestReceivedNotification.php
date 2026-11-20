<?php

declare(strict_types=1);

namespace App\Modules\Notification\Notifications;

use App\Modules\Friendship\Models\Friendship;
use App\Modules\Notification\Enums\NotificationType;
use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class FriendRequestReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private bool $isDeletionBroadcast = false;

    public function __construct(
        private int $actorId,
        private string $actorName,
        private int $friendshipId,
    ) {
        $this->afterCommit();
    }

    /**
     * Broadcast the deletion of the stored notification with the given id.
     */
    public static function deletion(string $notificationId): self
    {
        $notification = new self(0, '', 0);
        $notification->id = $notificationId;
        $notification->isDeletionBroadcast = true;

        return $notification;
    }

    /**
     * @return list<string>
     */
    public function via(User $user): array
    {
        return $this->isDeletionBroadcast ? ['broadcast'] : ['database', 'broadcast'];
    }

    /**
     * Deliver only while the request is still pending and addressed to an active account.
     */
    public function shouldSend(User $user, string $channel): bool
    {
        if ($this->isDeletionBroadcast) {
            return true;
        }

        if ($user->isSuspended()) {
            return false;
        }

        return Friendship::query()
            ->whereKey($this->friendshipId)
            ->where('receiver_id', $user->id)
            ->pending()
            ->exists();
    }

    public function databaseType(User $user): string
    {
        return NotificationType::FriendRequestReceived->value;
    }

    public function broadcastType(): string
    {
        return NotificationType::FriendRequestReceived->value;
    }

    /**
     * @return array{deleted: true}|array{
     *     summary: string,
     *     actors: list<array{id: int, name: string}>,
     *     target: array{postId: null, commentId: null, friendshipId: int}
     * }
     */
    public function toArray(User $user): array
    {
        if ($this->isDeletionBroadcast) {
            return ['deleted' => true];
        }

        return [
            'summary' => TranslatedText::of('notification/center.summaries.friend_request_received', [
                'actor' => $this->actorName,
            ]),
            'actors' => [
                [
                    'id' => $this->actorId,
                    'name' => $this->actorName,
                ],
            ],
            'target' => [
                'postId' => null,
                'commentId' => null,
                'friendshipId' => $this->friendshipId,
            ],
        ];
    }
}
