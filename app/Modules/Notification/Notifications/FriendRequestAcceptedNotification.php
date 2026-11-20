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

class FriendRequestAcceptedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private int $actorId,
        private string $actorName,
        private int $friendshipId,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(User $user): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Deliver only while the friendship is still accepted and the sender's account is active.
     */
    public function shouldSend(User $user, string $channel): bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        return Friendship::query()
            ->whereKey($this->friendshipId)
            ->where('requester_id', $user->id)
            ->accepted()
            ->exists();
    }

    public function databaseType(User $user): string
    {
        return NotificationType::FriendRequestAccepted->value;
    }

    public function broadcastType(): string
    {
        return NotificationType::FriendRequestAccepted->value;
    }

    /**
     * @return array{
     *     summary: string,
     *     actors: list<array{id: int, name: string}>,
     *     target: array{postId: null, commentId: null, friendshipId: int}
     * }
     */
    public function toArray(User $user): array
    {
        return [
            'summary' => TranslatedText::of('notification/center.summaries.friend_request_accepted', [
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
