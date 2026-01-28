<?php

namespace App\User\Resources;

use App\User\Enums\UserImageType;
use App\User\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Fortify\Features;

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
            'profilePicture' => $this->getFirstMediaUrl(UserImageType::PROFILE_PICTURE->value()),
            'coverImage' => $this->getFirstMediaUrl(UserImageType::COVER_IMAGE->value()),
            'twoFactorEnabled' => $this->hasEnabledTwoFactorAuthentication(),
            'requiresConfirmation' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm')
        ];
    }
}