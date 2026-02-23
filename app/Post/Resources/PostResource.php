<?php

namespace App\Post\Resources;

use App\Post\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Post */
class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'image' => $this->getFirstMediaUrl('posts_images'),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
