<?php

namespace App\User\Resources;

use App\User\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource {
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'profile_picture' => $this->getFirstMediaUrl('profile_picture'),
            'cover_image' => $this->getFirstMediaUrl('cover_image'),
        ];
    }
}