<?php

declare(strict_types=1);

namespace App\Modules\Notification\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;
use Override;

/** @mixin DatabaseNotification */
class NotificationResource extends JsonResource
{
    /**
     * Transform a stored notification into the public notification center contract.
     *
     * @return array{
     *     id: string,
     *     type: string,
     *     summary: string,
     *     actors: list<array{id: int, name: string}>,
     *     target: array{postId: int|null, commentId: int|null},
     *     readAt: string|null,
     *     createdAt: string|null
     * }
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $notificationData = $this->data;
        $summary = $notificationData['summary'] ?? null;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'summary' => \is_string($summary) ? $summary : '',
            'actors' => $this->actorSnapshots($notificationData['actors'] ?? null),
            'target' => $this->targetSnapshot($notificationData['target'] ?? null),
            'readAt' => $this->read_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Keep only the public id and name in each actor snapshot.
     *
     * @return list<array{id: int, name: string}>
     */
    private function actorSnapshots(mixed $actors): array
    {
        if (! \is_array($actors)) {
            return [];
        }

        $snapshots = [];

        foreach ($actors as $actor) {
            if (! \is_array($actor)) {
                continue;
            }

            $actorId = $actor['id'] ?? null;
            $actorName = $actor['name'] ?? null;

            if (! \is_int($actorId) || ! \is_string($actorName)) {
                continue;
            }

            $snapshots[] = [
                'id' => $actorId,
                'name' => $actorName,
            ];
        }

        return $snapshots;
    }

    /**
     * Keep only post and comment ids in the destination snapshot.
     *
     * @return array{postId: int|null, commentId: int|null}
     */
    private function targetSnapshot(mixed $target): array
    {
        if (! \is_array($target)) {
            return [
                'postId' => null,
                'commentId' => null,
            ];
        }

        $postId = $target['postId'] ?? null;
        $commentId = $target['commentId'] ?? null;

        return [
            'postId' => \is_int($postId) ? $postId : null,
            'commentId' => \is_int($commentId) ? $commentId : null,
        ];
    }
}
