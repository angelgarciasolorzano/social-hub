<?php

declare(strict_types=1);

namespace App\Modules\Notification\Notifications;

use App\Support\Translation\TranslatedText;
use App\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class RepliedToCommentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private const string TYPE = 'comment_reply';

    public function __construct(
        private int $actorId,
        private string $actorName,
        private int $postId,
        private int $commentId,
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $user): array
    {
        return ['database', 'broadcast'];
    }

    public function databaseType(User $user): string
    {
        return self::TYPE;
    }

    public function broadcastType(): string
    {
        return self::TYPE;
    }

    /**
     * @return array{
     *     summary: string,
     *     actors: list<array{id: int, name: string}>,
     *     target: array{postId: int, commentId: int}
     * }
     */
    public function toArray(User $user): array
    {
        return [
            'summary' => TranslatedText::of('notification/center.summaries.reply_to_comment', [
                'actor' => $this->actorName,
            ]),
            'actors' => [
                [
                    'id' => $this->actorId,
                    'name' => $this->actorName,
                ],
            ],
            'target' => [
                'postId' => $this->postId,
                'commentId' => $this->commentId,
            ],
        ];
    }
}
