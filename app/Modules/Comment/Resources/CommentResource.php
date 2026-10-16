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
        return [
            'id' => $this->id,
            'content' => $this->content,
            'createdAt' => $this->created_at?->toIso8601String(),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'repliedTo' => $this->whenLoaded('repliedToUser', fn (): ?array => $this->repliedTo()),
            'repliesInfo' => [
                'hasReplies' => $this->hasReplies(),
                'repliesCount' => $this->repliesCount(),
            ],
            'likesInfo' => [
                'count' => $this->likesCount(),
                'likedByMe' => $this->isLikedBy($request->user()),
            ],
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
