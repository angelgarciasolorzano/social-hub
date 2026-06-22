<?php

declare(strict_types=1);

namespace App\User\Resources;

use App\User\Enums\UserImageType;
use App\User\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'profilePicture' => $this->getFirstMediaUrl(UserImageType::PROFILE_PICTURE->value()),
            'coverImage' => $this->getFirstMediaUrl(UserImageType::COVER_IMAGE->value()),
        ];
    }
}
