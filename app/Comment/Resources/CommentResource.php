<?php

namespace App\Comment\Resources;

use App\Comment\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Comment */
class CommentResource extends JsonResource
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
            'created_at' => $this->created_at->diffForHumans(),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name
            ],
            'replies' => CommentResource::collection($this->whenLoaded('comments')),
        ];
    }
}
