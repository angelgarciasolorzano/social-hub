<?php

declare(strict_types=1);

namespace App\Modules\Post\Resources;

use App\Modules\Post\Models\Post;
use App\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin Post */
class PostResource extends JsonResource
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
            'image' => $this->getFirstMediaUrl('posts_images'),
            'createdAt' => $this->created_at?->toIso8601String(),
            'commentsCount' => $this->commentsTotal(),
            'likesInfo' => $this->likesInfo($request),
        ];
    }

    /**
     * Get the number of likes and whether the authenticated user of the request liked the post.
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
}
