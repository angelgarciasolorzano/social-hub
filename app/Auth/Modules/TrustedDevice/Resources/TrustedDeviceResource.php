<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Resources;

use App\Auth\Models\TrustedDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin TrustedDevice */
class TrustedDeviceResource extends JsonResource
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
            'name' => $this->name,
            'userAgent' => $this->user_agent,
            'browser' => $this->browser,
            'osName' => $this->os_name,
            'osVersion' => $this->os_version,
            'ip' => $this->ip,
            'lastUsedAt' => $this->last_used_at?->toIso8601String(),
            'expiresAt' => $this->expires_at->toIso8601String(),
            'isActive' => $this->isActive(),
        ];
    }
}
