<?php

declare(strict_types=1);

namespace App\Modules\Comment\Resources;

use App\Modules\Comment\Models\Comment;
use App\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin Comment */
class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        if ($this->trashed()) {
            return $this->tombstone();
        }

        return [
            'id' => $this->id,
            'content' => $this->content,
            'createdAt' => $this->created_at?->toIso8601String(),
            'editedAt' => $this->edited_at?->toIso8601String(),
            'isDeleted' => false,
            'isMine' => $this->isMine($request),
            'user' => $this->author(),
            'repliedTo' => $this->whenLoaded('repliedToUser', fn (): ?array => $this->repliedTo()),
            'repliesInfo' => $this->repliesInfo(),
            'likesInfo' => $this->likesInfo($request),
        ];
    }

    /**
     * The shape of a deleted comment that keeps its thread alive, without its text or its author.
     *
     * @return array<string, mixed>
     */
    private function tombstone(): array
    {
        return [
            'id' => $this->id,
            'content' => null,
            'createdAt' => $this->created_at?->toIso8601String(),
            'editedAt' => null,
            'isDeleted' => true,
            'isMine' => false,
            'user' => null,
            'repliesInfo' => $this->repliesInfo(),
            'likesInfo' => ['count' => 0, 'likedByMe' => false],
        ];
    }

    /**
     * Check if the authenticated user of the request wrote the comment, which is false for guests.
     */
    private function isMine(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User && $this->user_id === $user->id;
    }

    /**
     * Get the id and name of the author.
     *
     * @return array{id: int, name: string}
     */
    private function author(): array
    {
        return ['id' => $this->user->id, 'name' => $this->user->name];
    }

    /**
     * Get the number of likes and whether the authenticated user of the request liked the comment.
     *
     * @return array{count: int, likedByMe: bool}
     */
    private function likesInfo(Request $request): array
    {
        $user = $request->user();

        return [
            'count' => $this->likesCount(),
            'likedByMe' => $user instanceof User && $this->isLikedBy($user),
        ];
    }

    /**
     * Get whether the comment has replies and how many.
     *
     * @return array{hasReplies: bool, repliesCount: int}
     */
    private function repliesInfo(): array
    {
        return [
            'hasReplies' => $this->hasReplies(),
            'repliesCount' => $this->repliesCount(),
        ];
    }

    /**
     * Get the id and name of the user this comment replies to, or null when it mentions no one.
     *
     * @return array{id: int, name: string}|null
     */
    private function repliedTo(): ?array
    {
        $repliedToUser = $this->repliedToUser;

        if (! $repliedToUser instanceof User) {
            return null;
        }

        return ['id' => $repliedToUser->id, 'name' => $repliedToUser->name];
    }
}
