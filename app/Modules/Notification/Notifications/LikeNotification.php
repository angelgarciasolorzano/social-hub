<?php

declare(strict_types=1);

namespace App\Modules\Notification\Notifications;

use App\User\Models\User;
use Illuminate\Notifications\Notification;

final class LikeNotification extends Notification
{
    public const string TYPE = 'likes_grouped';

    /**
     * @param  list<array{id: int, name: string}>  $actors
     * @param  array{postId: int, commentId: int|null}  $target
     */
    public function __construct(
        private array $actors,
        private array $target,
        string $id,
        private readonly bool $isDeletionBroadcast = false,
    ) {
        $this->id = $id;
    }

    /**
     * @return list<string>
     */
    public function via(User $user): array
    {
        return ['broadcast'];
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
     * @return array{deleted: true}|array{
     *     summary: string,
     *     actors: list<array{id: int, name: string}>,
     *     target: array{postId: int, commentId: int|null}
     * }
     */
    public function toArray(User $user): array
    {
        if ($this->isDeletionBroadcast) {
            return ['deleted' => true];
        }

        $actorCount = \count($this->actors);
        $summaryKey = $this->target['commentId'] === null ? 'liked_post' : 'liked_comment';
        $actorName = $this->actors[$actorCount - 1]['name'] ?? '';
        $originalLocale = app()->getLocale();

        try {
            app()->setLocale($user->preferredLocale());

            $summary = trans_choice(
                "notification/center.summaries.{$summaryKey}",
                $actorCount,
                [
                    'actor' => $actorName,
                    'others' => max(0, $actorCount - 1),
                ],
            );
        } finally {
            app()->setLocale($originalLocale);
        }

        return [
            'summary' => $summary,
            'actors' => $this->actors,
            'target' => $this->target,
        ];
    }
}
