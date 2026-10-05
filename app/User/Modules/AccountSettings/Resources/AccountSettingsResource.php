<?php

declare(strict_types=1);

namespace App\User\Modules\AccountSettings\Resources;

use App\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin User */
class AccountSettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     phone: string|null,
     *     preferredLocale: string,
     *     biography: string|null,
     *     createdAt: string|null,
     *     lastLoginAt: string|null
     * }
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'preferredLocale' => $this->preferred_locale->value,
            'biography' => $this->biography,
            'createdAt' => $this->created_at?->toIso8601String(),
            'lastLoginAt' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
