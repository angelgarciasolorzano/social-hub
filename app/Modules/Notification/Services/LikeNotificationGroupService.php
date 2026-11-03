<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services;

use App\Modules\Comment\Enums\CommentType;
use App\Modules\Comment\Models\Comment;
use App\Modules\Notification\Notifications\LikeNotification;
use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LikeNotificationGroupService
{
    /**
     * Add or remove an actor from the unread group for a destination.
     */
    public function sync(User $recipient, User $actor, Comment|Post $likeable, bool $liked): void
    {
        if ($recipient->is($actor)) {
            return;
        }

        DB::transaction(function () use ($recipient, $actor, $likeable, $liked): void {
            $lockedLikeable = $this->lockLikeable($likeable);

            if ($lockedLikeable === null) {
                return;
            }

            $target = $this->targetFor($lockedLikeable);

            if ($target === null) {
                return;
            }

            $builder = $recipient->notifications()
                ->where('type', LikeNotification::TYPE)
                ->whereNull('read_at');

            $builder->getQuery()->getQuery()
                ->where('data->target->postId', $target['postId'])
                ->where('data->target->commentId', $target['commentId']);

            $group = $builder->lockForUpdate()->first();
            $actors = $this->actorsFor($group);
            $actorIsPresent = \in_array($actor->id, array_column($actors, 'id'), true);

            if ($liked && $actorIsPresent) {
                return;
            }

            if (! $liked && ! $actorIsPresent) {
                return;
            }

            $notificationId = $group instanceof DatabaseNotification
                ? $group->getKey()
                : Str::uuid()->toString();

            if (! \is_string($notificationId)) {
                return;
            }

            if ($liked) {
                $actors[] = [
                    'id' => $actor->id,
                    'name' => $actor->name,
                ];
            } else {
                $actors = array_values(array_filter(
                    $actors,
                    fn (array $existingActor): bool => $existingActor['id'] !== $actor->id,
                ));
            }

            if ($actors === [] && $group instanceof DatabaseNotification) {
                $group->delete();

                $this->broadcastAfterCommit(
                    $recipient,
                    new LikeNotification([], $target, $notificationId),
                );

                return;
            }

            $likeNotification = new LikeNotification($actors, $target, $notificationId);
            $payload = $likeNotification->toArray($recipient);

            if ($group instanceof DatabaseNotification) {
                $group->forceFill(['data' => $payload])->save();
            } else {
                $recipient->notifications()->create(['id' => $notificationId, 'type' => $likeNotification->databaseType($recipient), 'data' => $payload, 'read_at' => null]);
            }

            $this->broadcastAfterCommit($recipient, $likeNotification);
        }, attempts: 3);
    }

    /**
     * Lock the destination row so the first likes cannot create competing groups.
     */
    private function lockLikeable(Comment|Post $likeable): Comment|Post|null
    {
        $lockedLikeable = $likeable->newQuery()
            ->whereKey($likeable->getKey())
            ->lockForUpdate()
            ->first();

        return $lockedLikeable instanceof Comment || $lockedLikeable instanceof Post
            ? $lockedLikeable
            : null;
    }

    /**
     * Resolve the post and optional comment ids used by the public notification payload.
     *
     * @return array{postId: int, commentId: int|null}|null
     */
    private function targetFor(Comment|Post $likeable): ?array
    {
        if ($likeable instanceof Post) {
            return [
                'postId' => $likeable->id,
                'commentId' => null,
            ];
        }

        $threadComment = $likeable->commentable_type === CommentType::COMMENT
            ? Comment::withTrashed()->find($likeable->commentable_id)
            : $likeable->commentable;

        if ($threadComment instanceof Comment) {
            $post = $threadComment->commentable;
        } else {
            $post = $threadComment;
        }

        if (! $post instanceof Post) {
            return null;
        }

        return [
            'postId' => $post->id,
            'commentId' => $likeable->id,
        ];
    }

    /**
     * Read the actor snapshots stored in a notification payload.
     *
     * @return list<array{id: int, name: string}>
     */
    private function actorsFor(?DatabaseNotification $databaseNotification): array
    {
        $storedActors = $databaseNotification?->data['actors'] ?? [];

        if (! \is_array($storedActors)) {
            return [];
        }

        $actors = [];

        foreach ($storedActors as $storedActor) {
            if (! \is_array($storedActor)) {
                continue;
            }

            $actorId = $storedActor['id'] ?? null;
            $actorName = $storedActor['name'] ?? null;

            if (! \is_int($actorId) || ! \is_string($actorName)) {
                continue;
            }

            $actors[] = [
                'id' => $actorId,
                'name' => $actorName,
            ];
        }

        return $actors;
    }

    /**
     * Deliver the changed group after every enclosing transaction commits.
     */
    private function broadcastAfterCommit(User $user, LikeNotification $likeNotification): void
    {
        DB::afterCommit(function () use ($user, $likeNotification): void {
            $user->notify($likeNotification);
        });
    }
}
