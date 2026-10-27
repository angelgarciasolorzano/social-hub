<?php

declare(strict_types=1);

namespace App\Modules\Announcement\Resources;

use App\Modules\Announcement\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * An announcement as the application banner shows it: only what the user sees, with the body
 * already sanitized. The author, audience and dates stay on the server.
 *
 * @mixin Announcement
 */
class AnnouncementBannerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: int,
     *     title: string,
     *     bodyHtml: string,
     *     type: string,
     *     buttonLabel: string|null,
     *     buttonUrl: string|null,
     *     isDismissible: bool,
     *     imageUrl: string|null
     * }
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $imageUrl = $this->getFirstMediaUrl(Announcement::IMAGE_MEDIA_COLLECTION);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'bodyHtml' => $this->safeBodyHtml(),
            'type' => $this->type->value,
            'buttonLabel' => $this->button_label,
            'buttonUrl' => $this->button_url,
            'isDismissible' => $this->is_dismissible,
            'imageUrl' => $imageUrl === '' ? null : $imageUrl,
        ];
    }
}
