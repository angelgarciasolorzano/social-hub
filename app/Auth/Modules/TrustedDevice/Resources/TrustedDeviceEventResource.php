<?php

declare(strict_types=1);

namespace App\Auth\Modules\TrustedDevice\Resources;

use App\Auth\Models\TrustedDeviceEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin TrustedDeviceEvent */
class TrustedDeviceEventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: int,
     *     action: string,
     *     actionLabel: string,
     *     deviceLabel: string|null,
     *     deviceIsMobile: bool|null,
     *     deviceOsName: string|null,
     *     ip: string|null,
     *     createdAt: string|null,
     * }
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action->value,
            'actionLabel' => $this->action->label(),
            'deviceLabel' => $this->device_label,
            'deviceIsMobile' => $this->device_is_mobile,
            'deviceOsName' => $this->device_os_name,
            'ip' => $this->ip,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
