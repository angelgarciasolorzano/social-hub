<?php

declare(strict_types=1);

namespace App\Comment\Resources;

use App\Comment\Models\Comment;
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
            'repliesInfo' => [
                'hasReplies' => $this->hasReplies(),
                'repliesCount' => $this->repliesCount(),
            ],
        ];
    }
}
